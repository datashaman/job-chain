<?php

namespace Datashaman\JobChain;

trait HasJobChain
{
    protected JobChain $jobChain;
    protected string $jobKey;

    public function setJobChain(JobChain $jobChain)
    {
        $this->jobChain = $jobChain;
    }

    public function setJobKey(string $jobKey)
    {
        $this->jobKey = $jobKey;
    }

    public function done(mixed $value): void
    {
        $this->jobChain->done($this->jobKey, $value);
    }
}
