<?php

namespace Tests\Fixtures;

class JobThree extends AbstractJob
{
    public function __construct(
        public string $message
    ) {
    }

    public function handle()
    {
        $this->done(null, 'JobThree has run');
    }
}
