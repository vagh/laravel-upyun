<?php


namespace Vagh\Laravel\Upyun\plugins;

use League\Flysystem\Plugin\AbstractPlugin;

class GetUrl extends AbstractPlugin
{
    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'getUrl';
    }

    public function handle($path = null)
    {
        return $this->filesystem->getAdapter()->getUrl($path);
    }
}