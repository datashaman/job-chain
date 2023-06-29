<?php

namespace Tests\Fixtures;

use Datashaman\JobChain\HasJobChain;

class JobTwo
{
    use HasJobChain;

    public function handle()
    {
        $this->done(null, 'JobTwo has run');
    }
}
