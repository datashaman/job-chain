<?php

namespace Datashaman\JobChain;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class JobChainError implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        protected JobChain $jobChain,
        public string $jobKey,
        public mixed $error
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("job-chain.{$this->jobChain->getKey()}"),
        ];
    }
}
