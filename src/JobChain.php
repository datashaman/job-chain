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
    protected ?Model $user;
    protected string $key;
    protected string $done;
    protected string $namespace;
    protected int $lifetime;
    protected array $channels;

    public function __construct(
        protected string $name,
        array $config
    ) {
        Log::debug("Initializing JobChain with $name", [
            'config' => $config,
        ]);

        $this->jobs = collect($config['jobs']);

        $this->channels = $config['channels'] ?? [];
        $this->done = $config['done'] ?? $this->jobs->keys()->last();
        $this->key = $config['key'] ?? Str::uuid();
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
            'done' => $this->done,
            'jobs' => $this->jobs->toArray(),
            'key' => $this->key,
            'lifetime' => $this->lifetime,
        ];
    }

    /**
     * @throws RuntimeException
     */
    public function run(array $inputs = [], ?Model $user = null): void
    {
        $this->user = $user ?? auth()->user();

        foreach ($this->jobs as $jobKey => $job) {
            $jobParams = $job['params'] ?? [];
            $hasDependency = false;

            foreach ($jobParams as $input) {
                if ($input instanceof TaggedValue) {
                    $inputTag = $input->getTag();
                    $inputValue = $input->getValue();

                    if ($inputTag === 'input' && !Arr::has($inputs, $inputValue)) {
                        throw new RuntimeException("Input '{$inputValue}' is missing. Please provide it as parameter to the run method.");
                    }

                    if ($inputTag === 'job') {
                        Log::debug("Job {$jobKey} depends on another job, skipping");
                        $hasDependency = true;

                        break;
                    }
                }
            }

            if (!$hasDependency) {
                Log::debug("Job {$jobKey} has no job dependencies and inputs are met, dispatching");

                $jobParams = array_merge($jobParams, $inputs);

                $this->dispatchJob($jobKey, $jobParams);
            }
        }
    }

    public function done(string $jobKey, mixed $error = null, mixed $response = null): void
    {
        if ($error) {
            JobChainError::dispatch($this, $jobKey, $error);

            return;
        }

        if (!$this->isDone() && $jobKey === $this->done) {
            JobChainDone::dispatch($this, $jobKey, $response);
            Cache::put($this->getKey('done'), 1, $this->lifetime);

            return;
        } else {
            $this->putResponse($jobKey, $response);
        }

        $this
            ->jobs
            ->keys()
            ->each(
                function ($jobKey) {
                    if ($this->shouldDispatch($jobKey)) {
                        $this->dispatchJob($jobKey);
                    }
                }
            );
    }

    /**
     * @throws RuntimeException
     */
    public function getKey(string $key = '', string $suffix = ''): string
    {
        if ($suffix && !$key) {
            throw new RuntimeException('Cannot set suffix without key');
        }

        return collect([$this->key, $key, $suffix])
            ->filter()
            ->implode('.');
    }

    public function getChannels(): array
    {
        $privateChannel = "job-chain.{$this->name}";

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

    protected function getChannelRoute(string $route): string
    {
        $replacements = [
            '{user}' => $this->user->getKey(),
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $route
        );
    }

    protected function dispatchJob(string $jobKey, array $params = [])
    {
        $params = $this->getParams($this->jobs[$jobKey], $params);

        Log::debug("Dispatching job {$jobKey}", [
            'jobKey' => $jobKey,
            'params' => $params,
        ]);

        Cache::put($this->getKey($jobKey, 'dispatched'), 1, $this->lifetime);

        $type = $this->jobs[$jobKey]['type'];

        if ($this->namespace) {
            $type = "$this->namespace\\$type";
        }

        $job = App::make($type, $params);

        $job->setJobChain($this);
        $job->setJobKey($jobKey);

        return $this->dispatch($job);
    }

    protected function shouldDispatch($jobKey): bool
    {
        return !$this->wasDispatched($jobKey)
            && $this->dependenciesMet($jobKey);
    }

    protected function wasDispatched(string $key): bool
    {
        return (bool) Cache::get($this->getKey($key, 'dispatched'));
    }

    protected function isDone(): bool
    {
        return (bool) Cache::get($this->getKey('done'));
    }

    protected function dependenciesMet(string $jobKey): bool
    {
        $job = $this->jobs[$jobKey];

        return collect($job['data'] ?? [])
            ->values()
            ->filter(fn ($input) => $input instanceof TaggedValue && $input->getTag() === 'job')
            ->search(fn ($tag) => !$this->hasResponse($tag->getValue())) === false;
    }

    protected function hasResponse($jobKey): bool
    {
        return Cache::has($this->getKey($jobKey));
    }

    protected function getResponse(string $jobKey): mixed
    {
        return Cache::get($this->getKey($jobKey));
    }

    protected function putResponse(string $jobKey, mixed $response): void
    {
        Cache::put($this->getKey($jobKey), $response, $this->lifetime);
        JobChainResponse::dispatch($this, $jobKey, $response);
    }

    protected function getParams(array $job, array $params = []): array
    {
        $params = array_merge(
            $job['params'] ?? [],
            $params
        );

        return collect($params)
            ->map(
                function ($input) {
                    if ($input instanceof TaggedValue) {
                        $inputTag = $input->getTag();
                        $inputValue = $input->getValue();

                        if ($inputTag === 'job') {
                            [$inputJob, $inputKey] = array_pad(explode('.', $inputValue), 2, null);

                            $response = $this->getResponse($inputJob);

                            if ($inputKey) {
                                return Arr::get($response, $inputKey);
                            }

                            return $response;
                        }

                        throw new RuntimeException("Unhandled tag '$inputTag' with value '$inputValue'");
                    }

                    return $input;
                }
            )
            ->all();
    }
}
