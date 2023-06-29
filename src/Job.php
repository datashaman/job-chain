<?php

namespace Datashaman\JobChain;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Job implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected JobChain $chain,
        protected string $key,
        protected array $params
    ) {
    }

    protected function done($value)
    {
        $this->chain->done($this->key, $value);;
    }
}
