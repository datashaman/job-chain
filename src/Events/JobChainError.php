<?php

namespace Datashaman\JobChain\Events;

use Datashaman\JobChain\JobChain;

class JobChainError extends JobChainEvent
{
    public function __construct(
        JobChain $jobChain,
        public string $jobKey,
        public mixed $error
    ) {
        parent::__construct($jobChain);
    }
}
