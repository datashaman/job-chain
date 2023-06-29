# job-chain

Job chains powered by cache for Laravel.

## installation

```
composer require datashaman/job-chain
```

## instrumentation

```
use Datashaman\JobChain\HasJobChain;

class MyJob
{
    use HasJobChain;

    public handle()
    {
        // do things
        $value = 'my job has run';

        $this->done($value);
    }
}
```

## configuration

```
return [
    /**
     * Job chain loader will search through these paths for chain files.
     */
    'paths' => [
        base_path('chains'),
    ],

    /**
     * Cache store use for holding chain state.
     */
    'cache' => env('JOB_CHAIN_CACHE', env('CACHE_DRIVER', 'file')),

    /**
     * Cache item lifetime. This must be longer than the total expected
     * run time for any chain.
     *
     * This can overridden per chain.
     */
    'lifetime' = env('JOB_CHAIN_LIFETIME', 60 * 60 * 24),
];
```

## usage

Given this file `chains/chain1.yml`:

```
done: jobThree

lifetime: 86400

jobs:
  jobOne:
    type: JobOne
  jobTwo:
    type: JobTwo
    params:
      documents: !job jobOne
  jobThree:
    type: JobThree
    params:
      documents: !job jobTwo
```
And these three test job class:

```
use Datashaman\JobChain\HasJobChain;

class JobOne
{
    use HasJobChain;

    public function handle()
    {
        $this->done('JobOne has run');
    }
}

class JobTwo
{
    use HasJobChain;

    public function handle()
    {
        $this->done('JobTwo has run');
    }
}

class JobThree
{
    use HasJobChain;

    public function handle()
    {
        $this->done('JobThree has run');
    }
}
```

When you run this code:

```
use JobChain;

Event::listen(function (JobChainDone $event) {
    logger()->info('Job chain done', [
        'jobChain' => $event->jobChain,
        'value' => $event->value,
    ]);
});

JobChain::run('chain1');
```

Execution will flow sequentially through `JobOne`, `JobTwo` and `JobThree` because of the dependency graph denoted by using a custom type of `!job` in the YAML definition.

The event listener should receive a value of `JobThree has run` which is the value the `done` job has submitted.

If the chain does not define a `done` job, it is assumed to be the last job in the definition.

Test build.
