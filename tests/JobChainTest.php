<?php

namespace Tests;

use Datashaman\JobChain\JobChainFacade as JobChain;
use Datashaman\JobChain\JobChainDone;
use Illuminate\Support\Facades\Event;

class JobChainTest extends TestCase
{
    public function testJobChain()
    {
        Event::fake();

        JobChain::run('chain1');

        Event::assertDispatched(function (JobChainDone $event) {
            return $event->value === 'JobThree has run';
        });
    }
}
