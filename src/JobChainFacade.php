<?php

namespace Datashaman\JobChain;

use Illuminate\Support\Facades\Facade;

class JobChainFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'job-chain';
    }
}
