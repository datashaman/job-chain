<?php

namespace Tests;

use Datashaman\JobChain\JobChainFacade as JobChain;
use Datashaman\JobChain\JobChainDone;
use Datashaman\JobChain\JobChainResponse;
use Illuminate\Support\Facades\Event;

class JobChainTest extends TestCase
{
    public function testJobChain()
    {
        Event::fake();

        JobChain::run('chain1');

        Event::assertDispatched(function (JobChainResponse $event) {
            return $event->response === 'JobOne has run'
                && $event->jobKey === 'jobOne';
        });

        Event::assertDispatched(function (JobChainResponse $event) {
            return $event->response === 'JobTwo has run'
                && $event->jobKey === 'jobTwo';
        });

        Event::assertDispatched(function (JobChainResponse $event) {
            return $event->response === 'JobThree has run'
                && $event->jobKey === 'jobThree';
        });

        Event::assertDispatched(function (JobChainDone $event) {
            return $event->response === 'JobThree has run';
        });
    }
}
