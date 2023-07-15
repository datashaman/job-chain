<?php

namespace Tests\Fixtures;

use Datashaman\JobChain\HasJobChain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class AbstractJob implements ShouldQueue
{
    use Dispatchable;
    use HasJobChain;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
}
