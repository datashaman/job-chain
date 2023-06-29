<?php

namespace Datashaman\JobChain;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class JobChainDone implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        protected JobChain $jobChain,
        public mixed $response
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("job-chain.{$this->jobChain->getKey()}"),
        ];
    }
}
