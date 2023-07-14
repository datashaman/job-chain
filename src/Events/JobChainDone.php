<?php

namespace Datashaman\JobChain\Events;

use Datashaman\JobChain\JobChain;

class JobChainDone extends JobChainEvent
{
    public function __construct(
        JobChain $jobChain,
        public string $jobKey,
        public mixed $response
    ) {
        parent::__construct($jobChain);
    }
}
