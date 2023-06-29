<?php

namespace Tests\Fixtures;

use Datashaman\JobChain\HasJobChain;

class JobTwo
{
    use HasJobChain;

    public function handle()
    {
        $this->done('JobTwo has run');
    }
}
