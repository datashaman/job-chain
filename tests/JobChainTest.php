<?php

namespace Tests;

use Datashaman\JobChain\JobChainFacade as JobChain;
use Datashaman\JobChain\Events\JobChainDone;
use Datashaman\JobChain\Events\JobChainResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class JobChainTest extends TestCase
{
    public function testJobChain()
    {
        Queue::fake();

        JobChain::run('chain1', [
            'filePath' => 'test.txt',
        ]);

        Queue::assertPushed(function (Fixtures\JobOne $job) {
            return $job->filePath == 'test.txt';
        });

        Queue::assertPushed(function (Fixtures\JobTwo $job) {
            dd($job);
        });

        Queue::assertPushed(function (Fixtures\JobThree $job) {
            dd($job);
        });
    }
}
