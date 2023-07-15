<?php

namespace Datashaman\JobChain;

use Datashaman\JobChain\Events\JobChainDone;
use Datashaman\JobChain\Events\JobChainError;
use Datashaman\JobChain\Events\JobChainResponse;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

class JobChain
{
    use DispatchesJobs;

    protected Collection $jobs;
    protected ?Model $user = null;
    protected string $done;
    protected string $namespace;
    protected string $runId;
    protected int $lifetime;
    protected array $channels;
    protected array $params;

    public function __construct(
        protected string $name,
        array $config
    ) {
        Log::debug("Initializing JobChain named {$this->name}", [
            'config' => $config,
        ]);

        if (Arr::has($config, 'name')) {
            $this->name = $config['name'];
        }

        $this->jobs = collect($config['jobs']);

        $this->channels = $config['channels'] ?? [];
        $this->done = $config['done'] ?? $this->jobs->keys()->last();
        $this->lifetime = $config['lifetime'] ?? config('job-chain.lifetime');
        $this->namespace = trim($config['namespace'] ?? '', "\\ \n\r\t\v\x00");
    }

    public function writeToYaml(string $path): self
    {
        $contents = Yaml::dump($this->toArray(), 5);
        file_put_contents($path, $contents);

        return $this;
    }

    public function toArray(): array
    {
        return [
            'runId' => $this->runId,
            'channels' => $this->channels,
            'done' => $this->done,
            'lifetime' => $this->lifetime,
            'namespace' => $this->namespace,
            'jobs' => $this->jobs->toArray(),
            'user' => $this->user->toArray(),
        ];
    }

    /**
     * @throws RuntimeException
     */
    public function run(array $params = [], ?Model $user = null): void
    {
        $this->params = $params;

        $this->runId = (string) Str::uuid();
        $this->user = $user ?? auth()->user();

        $this
            ->jobs
            ->keys()
            ->filter($this->dependenciesMet(...))
            ->each(function ($jobKey) {
                if ($this->isDone()) {
                    return false;
                }

                Log::debug("Job {$jobKey} dependencies are met, dispatching");
                $this->dispatchJob($jobKey);
            });
    }

    public function done(string $jobKey, mixed $error = null, mixed $response = null): void
    {
        if ($this->isDone()) {
            return;
        }

        if ($error) {
            JobChainError::dispatch($this, $jobKey, $error);

            return;
        }

        if ($jobKey === $this->done) {
            Cache::put($this->getCacheKey('done'), 1, $this->lifetime);
            JobChainDone::dispatch($this, $jobKey, $response);

            return;
        }

        $this->putResponse($jobKey, $response);

        $this
            ->jobs
            ->each(fn (&$value) => $this->substituteParams($value['params']))
            ->keys()
            ->filter($this->shouldDispatch(...))
            ->each($this->dispatchJob(...));
    }

    /**
     * @throws RuntimeException
     */
    public function getCacheKey(string $key = '', string $suffix = ''): string
    {
        if ($suffix && !$key) {
            throw new RuntimeException('Cannot set suffix without key');
        }

        return implode('.', array_filter([
            'job-chain',
            $this->name,
            $this->runId,
            $key,
            $suffix
        ]));
    }

    public function broadcastOn(): array
    {
        $privateChannel = "job-chain.{$this->name}.{$this->runId}";

        $channels = [
            new PrivateChannel($privateChannel),
        ];

        foreach ($this->channels as $channel) {
            $visibility = $channel['visibility'] ?? 'private';
            $class = $visibility === 'private' ? PrivateChannel::class : Channel::class;
            $channels[] = new $class($this->getChannelRoute($channel['route'] ?? ''));
        }

        return $channels;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getDone(): string
    {
        return $this->done;
    }

    public function getJobs(): Collection
    {
        return $this->jobs;
    }

    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    protected function getChannelRoute(string $route): string
    {
        $replacements = [
            '{user}' => $this->user?->getKey() ?? '',
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $route
        );
    }

    protected function dispatchJob(string $jobKey): void
    {
        $params = $this->getJobParams($jobKey);

        Log::debug("Dispatching job {$jobKey}", [
            'jobKey' => $jobKey,
            'params' => $params,
        ]);

        Cache::put($this->getCacheKey($jobKey, 'dispatched'), 1, $this->lifetime);

        $type = $this->jobs[$jobKey]['type'];

        if ($this->namespace) {
            $type = "$this->namespace\\$type";
        }

        $job = App::makeWith($type, $params);

        $job->setJobChain($this);
        $job->setJobKey($jobKey);

        $this->dispatch($job);
    }

    protected function shouldDispatch(string $jobKey): bool
    {
        return !$this->wasDispatched($jobKey)
            && $this->dependenciesMet($jobKey);
    }

    protected function wasDispatched(string $key): bool
    {
        return (bool) Cache::get($this->getCacheKey($key, 'dispatched'));
    }

    protected function isDone(): bool
    {
        return (bool) Cache::get($this->getCacheKey('done'));
    }

    protected function dependenciesMet(string $jobKey): bool
    {
        $job = $this->jobs[$jobKey];

        return collect($job['params'] ?? [])
            ->values()
            ->filter(fn ($param) => $param instanceof TaggedValue && $param->getTag() === 'job')
            ->search(fn ($tag) => !$this->hasResponse($tag->getValue())) === false;
    }

    protected function hasResponse(string $jobKey): bool
    {
        return Cache::has($this->getCacheKey($jobKey));
    }

    protected function getResponse(string $jobKey): mixed
    {
        return Cache::get($this->getCacheKey($jobKey));
    }

    protected function putResponse(string $jobKey, mixed $response): void
    {
        Log::debug("Put response for '{$jobKey}'", [
            'response' => $response,
        ]);
        Cache::put($this->getCacheKey($jobKey), $response, $this->lifetime);
        JobChainResponse::dispatch($this, $jobKey, $response);
    }

    protected function substituteParams(array &$params): void
    {
        array_walk_recursive($params, $this->substituteParam(...));

        if (Arr::has($params, 'messages')) {
            foreach ($params['messages'] as &$message) {
                array_walk_recursive($message, $this->substituteParam(...));
            }
        }
    }

    protected function substituteParam(&$value): void
    {
        if ($value instanceof TaggedValue) {
            $parts = preg_split('/\s+/', $value->getValue());

            switch ($value->getTag()) {
                case 'param':
                    $paramKey = $parts[0];

                    if (!Arr::has($this->params, $paramKey) && count($parts) < 2) {
                        throw new RuntimeException("Parameter '{$paramKey}' is missing and has no default. Please provide a value when calling the run method.");
                    }

                    $value = Arr::get($this->params, $paramKey, $parts[1] ?? null);
                    break;
                case 'job':
                    $jobKey = $parts[0];
                    $jobResponseKey = $parts[1] ?? null;

                    if ($this->hasResponse($jobKey)) {
                        $response = $this->getResponse($jobKey);
                        $value = $jobResponseKey ? Arr::get($response, $jobResponseKey) : $response;
                    }
                    break;
            }
        }
    }

    protected function getJobParams(string $jobKey): array
    {
        $job = $this->jobs[$jobKey];
        $jobParams = $job['params'] ?? [];

        $this->substituteParams($jobParams);

        return $jobParams;
    }
}
