<?php

namespace Datashaman\JobChain;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class JobChainLoader
{
    public function __construct(
        protected Filesystem $files,
        protected array $paths
    ) {
    }

    public function load(string $chain): JobChain
    {
        $chain = str_replace('.', DIRECTORY_SEPARATOR, $chain);

        foreach ($this->paths as $path) {
            $path = $path . DIRECTORY_SEPARATOR . $chain . '.yml';

            if ($this->files->exists($path)) {
                return $this->loadFromYaml($path);
            }
        }

        throw new RuntimeException('Job chain not found');
    }

    public function run(string $chain, array $params = [])
    {
        return $this->load($chain)->run($params);
    }

    protected function loadFromYaml(string $path): JobChain
    {
        $config = Yaml::parseFile($path, Yaml::PARSE_CUSTOM_TAGS);

        return new JobChain(
            $config['jobs'],
            $config['key'] ?? '',
            $config['done'] ?? '',
        );
    }
}   
