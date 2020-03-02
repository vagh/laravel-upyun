<?php


namespace Vagh\Laravel\Upyun\plugins;

use League\Flysystem\Config;
use League\Flysystem\Plugin\AbstractPlugin;

class PutFile extends AbstractPlugin
{
    /**
     * Get the method name.
     * @return string
     */
    public function getMethod()
    {
        return 'putFile';
    }

    /**
     * @param $path
     * @param $filePath
     * @param array $options
     *
     * @return bool
     */
    public function handle($path, $filePath, array $options = [])
    {
        $config = new Config($options);

        return (bool) $this->filesystem->getAdapter()->writeFile($path, $filePath, $config);
    }
}