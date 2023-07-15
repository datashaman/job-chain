<?php

namespace Tests\Fixtures;

class JobOne extends AbstractJob
{
    public function __construct(
        public string $filePath
    ) {
    }

    public function handle()
    {
        $this->done(null, 'JobOne has run');
    }
}
