<?php

namespace Tests\Fixtures;

use Datashaman\JobChain\HasJobChain;

class JobThree
{
    use HasJobChain;

    public function handle()
    {
        $this->done(null, 'JobThree has run');
    }
}
