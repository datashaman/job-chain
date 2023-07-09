<?php

namespace Datashaman\JobChain;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class JobChainServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/job-chain.php', 'job-chain'
        );

        $this->app->singleton('job-chain', function (Container $app): JobChainLoader {
            return $app->make(JobChainLoader::class, [
                'paths' => config('job-chain.paths'),
            ]);
        });
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/job-chain.php' => config_path('job-chain.php'),
        ]);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['job-chain'];
    }
}
