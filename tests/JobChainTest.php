<?php

namespace Tests;

use Datashaman\JobChain\JobChainFacade as JobChain;
use Datashaman\JobChain\JobChainDone;
use Illuminate\Support\Facades\Event;

class JobChainTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->jobChain = JobChain::load('chain1');
    }

    public function testJobChain()
    {
        Event::fake();

        $this->jobChain->run();

        Event::assertDispatched(function (JobChainDone $event) {
            return $event->value === 'JobThree has run';
        });
    }
}
