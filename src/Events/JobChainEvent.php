<?php

namespace Datashaman\JobChain\Events;

use Datashaman\JobChain\JobChain;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

abstract class JobChainEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        protected JobChain $jobChain
    ) {
    }

    public function broadcastOn(): array
    {
        return $this->jobChain->broadcastOn();
    }
}
