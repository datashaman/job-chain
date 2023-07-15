<?php

namespace Tests\Fixtures;

class JobTwo extends AbstractJob
{
    public function __construct(
        public string $message
    ) {
    }

    public function handle()
    {
        $this->done(null, 'JobTwo has run');
    }
}
