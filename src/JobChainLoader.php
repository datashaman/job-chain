<?php

namespace Datashaman\JobChain;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class JobChainLoader
{
    public function __construct(
        protected Filesystem $files,
        protected array $paths
    ) {
    }

    /**
     * @throws RuntimeException
     */
    public function load(string $chain): JobChain
    {
        $chain = str_replace('.', '/', $chain);

        foreach ($this->paths as $path) {
            $file = "$path/$chain.yml";

            if ($this->files->exists($file)) {
                return $this->loadFromYaml($file);
            }
        }

        throw new RuntimeException('Job chain not found');
    }

    public function run(string $chain, array $params = []): void
    {
        $this->load($chain)->run($params);
    }

    protected function loadFromYaml(string $path): JobChain
    {
        $config = Yaml::parseFile($path, Yaml::PARSE_CUSTOM_TAGS);

        return new JobChain($config);
    }
}   
