<?php

namespace Datashaman\JobChain;

use Illuminate\Support\ServiceProvider;

class JobChainServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('job-chain', function ($app) {
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
    public function boot()
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
    public function provides()
    {
        return ['job-chain'];
    }
}
