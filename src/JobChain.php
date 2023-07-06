<?php

namespace Datashaman\JobChain;

use Exception;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

class JobChain
{
    use DispatchesJobs;

    protected Collection $jobs;
    protected string $key;
    protected string $done;
    protected string $namespace;
    protected int $lifetime;

    public function __construct(
        array $config
    ) {
        $this->jobs = collect($config['jobs']);
        $this->key = $config['key'] ?? Str::uuid();
        $this->done = $config['done'] ?? $this->jobs->keys()->last();
        $this->namespace = trim($config['namespace'] ?? '', "\\ \n\r\t\v\x00");
        $this->lifetime = $config['lifetime'] ?? config('job-chain.lifetime');
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

    public function run(array $inputs = [])
    {
        foreach ($this->jobs as $jobKey => $job) {
            $jobParams = $job['params'] ?? [];
            $hasDependency = false;

            foreach ($jobParams as $input) {
                if ($input instanceof TaggedValue) {
                    $inputTag = $input->getTag();
                    $inputValue = $input->getValue();

                    throw_if(
                        $inputTag === 'input' && !Arr::has($inputs, $inputValue),
                        RuntimeException::class,
                        "Input '${inputValue}' is missing. Please provide it as parameter to the run method."
                    );

                    if ($inputTag === 'job') {
                        $hasDependency = true;
                        break;
                    }
                }
            }

            if (!$hasDependency) {
                $jobParams = array_merge($jobParams, $inputs);

                $this->dispatchJob($jobKey, $jobParams);
            }
        }
    }

    public function done(string $jobKey, mixed $error = null, mixed $response = null)
    {
        if ($error) {
            JobChainError::dispatch($this, $jobKey, $error);

            return;
        }

        $this->putResponse($jobKey, $response);;

        $this
            ->jobs
            ->keys()
            ->each(
                function ($jobKey) {
                    if ($this->shouldDispatch($jobKey)) {
                        $this->dispatchJob($jobKey);
                    }

                    if (!$this->isDone() && $jobKey === $this->done) {
                        $response = $this->getResponse($jobKey);
                        JobChainDone::dispatch($this, $response);
                        Cache::put($this->getKey('done'), 1, $this->lifetime);

                        return false;
                    }
                }
            );
    }

    public function getKey(string $key = '', string $suffix = '')
    {
        throw_if(
            $suffix && !$key,
            RuntimeException::class,
            'Cannot set suffix without key'
        );

        return collect([$this->key, $key, $suffix])
            ->filter()
            ->implode('.');
    }

    protected function dispatchJob(string $jobKey, array $params = [])
    {
        Cache::put($this->getKey($jobKey, 'dispatched'), 1, $this->lifetime);

        $type = $this->jobs[$jobKey]['type'];

        if ($this->namespace) {
            $type = "{$this->namespace}\\{$type}";
        }

        $job = App::make(
            $type,
            $this->getParams($this->jobs[$jobKey], $params)
        );

        $job->setJobChain($this);
        $job->setJobKey($jobKey);

        $this->dispatch($job);
    }

    protected function shouldDispatch($jobKey)
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

    protected function putResponse(string $jobKey, mixed $response)
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

                        throw new RuntimeException("Unhandled tag '{$inputTag}' with value '{$inputValue}'");
                    }

                    return $input;
                }
            )
            ->all();
    }
}
