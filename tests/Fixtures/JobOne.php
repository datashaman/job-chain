<?php

namespace Tests\Fixtures;

use Datashaman\JobChain\HasJobChain;

class JobOne
{
    use HasJobChain;

    public function handle()
    {
        $this->done(null, 'JobOne has run');
    }
}
