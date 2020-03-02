<?php


namespace Vagh\Laravel\Upyun;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use Illuminate\Support\Arr;
use Upyun\Upyun;
use Upyun\Config as UpYunConfig;
use Exception;

class UpYunAdapter extends AbstractAdapter
{
    protected $config;
    protected $up_client;

    public function __construct($config)
    {
        $this->config = $config;
        $client_config = new UpYunConfig(
            Arr::get($config, 'bucket'),
            Arr::get($config, 'operator_name'),
            Arr::get($config, 'operator_password')
        );

        // 是否使用 https 协议
        $client_config->useSsl = Arr::get($config, 'use_ssl', false);

        $this->up_client = new Upyun($client_config);
    }

    /**
     * @param string $path
     * @param string $contents
     * @param Config $config
     * @return array|bool
     * @throws Exception
     */
    public function write($path, $contents, Config $config)
    {
        if (gettype($contents) == 'resource') {
            $contents = stream_get_contents($contents);
        }
        $object = $this->applyPathPrefix($path);

        try {
            $result = $this->up_client->write($object, $contents);
        } catch (Exception $e) {
            return false;
        }

        return $result;
    }

    /**
     * @param string $path
     * @param resource $resource
     * @param Config $config
     * @return array|bool
     * @throws Exception
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->write($path, $resource, $config);
    }

    /**
     * @param string $path
     * @param string $contents
     * @param Config $config
     * @return array|bool
     * @throws Exception
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * @param string $path
     * @param resource $resource
     * @param Config $config
     * @return array|bool
     * @throws Exception
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }

    /**
     * @param string $path
     * @param string $new_path
     * @return bool
     * @throws Exception
     */
    public function rename($path, $new_path)
    {
        try {
            $this->copy($path, $new_path);
            $this->delete($path);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * @param string $path
     * @param string $new_path
     * @return bool
     * @throws Exception
     */
    public function copy($path, $new_path)
    {
        $object = $this->applyPathPrefix($path);
        $newObject = $this->applyPathPrefix($new_path);

        try {
            $contents = $this->up_client->read($object);
            $this->up_client->write($newObject, $contents);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * @param string $path
     * @return bool
     * @throws Exception
     */
    public function delete($path)
    {
        $object = $this->applyPathPrefix($path);

        return $this->up_client->delete($object);
    }

    /**
     * @param string $dirname
     * @return bool
     * @throws Exception
     */
    public function deleteDir($dirname)
    {
        $dirname = $this->applyPathPrefix($dirname);

        return $this->up_client->deleteDir($dirname);
    }

    /**
     * @param string $dirname
     * @param Config $config
     * @return bool
     * @throws Exception
     */
    public function createDir($dirname, Config $config)
    {
        return $this->up_client->createDir($dirname);
    }

    /**
     * @param string $path
     * @param string $visibility
     * @return bool
     */
    public function setVisibility($path, $visibility)
    {
        return true;
    }

    /**
     * @param string $path
     * @return bool
     * @throws Exception
     */
    public function has($path)
    {
        return $this->up_client->has($path);
    }

    /**
     * @param string $path
     * @return array
     * @throws Exception
     */
    public function read($path)
    {
        $path_object = $this->applyPathPrefix($path);
        $contents = $this->up_client->read($path_object);

        return compact('contents', 'path');
    }

    /**
     * @param string $path
     * @return array | bool
     * @throws Exception
     */
    public function readStream($path)
    {
        if (!($result = $this->read($path))) {
            return false;
        }

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $result['contents']);
        rewind($stream);
        unset($result['contents']);

        return compact('stream', 'path');
    }

    /**
     * @param string $directory
     * @param bool $recursive
     * @return array
     * @throws Exception
     */
    public function listContents($directory = '', $recursive = false)
    {
        $list = [];

        $result = $this->up_client->read($directory, null, ['X-List-Limit' => 100, 'X-List-Iter' => null]);

        foreach ($result['files'] as $files) {
            $list[] = $this->normalizeFileInfo($files, $directory);
        }

        return $list;
    }

    /**
     * @param string $path
     * @return bool
     */
    public function getMetadata($path)
    {
        $object = $this->applyPathPrefix($path);
        $result = $this->up_client->info($object);

        return $this->formatUpYunMetaData($result);
    }

    /**
     * @param string $path
     * @return array
     */
    public function getType($path)
    {
        $response = $this->getMetadata($path);

        return ['type' => $response['x-upyun-file-type']];
    }

    /**
     * @param string $path
     * @return array
     */
    public function getSize($path)
    {
        $response = $this->getMetadata($path);

        return ['size' => $response['x-upyun-file-size']];
    }

    /**
     * @param string $path
     * @return array
     */
    public function getMimetype($path)
    {
        $headers = get_headers($this->getUrl($path), 1);
        $mime_type = $headers['Content-Type'];
        return compact('mime_type');
    }

    /**
     * @param string $path
     * @return array
     */
    public function getTimestamp($path)
    {
        $response = $this->getMetadata($path);

        return ['timestamp' => $response['x-upyun-file-date']];
    }

    /**
     * @param string $path
     * @return bool
     */
    public function getVisibility($path)
    {
        return true;
    }

    /**
     * Normalize the file info
     * @param array $stats
     * @param string $directory
     * @return array
     * @author yuzhihao <yu@vagh.cn>
     * @since 2020/3/2
     */
    protected function normalizeFileInfo(array $stats, string $directory)
    {
        $filePath = ltrim($directory . '/' . $stats['name'], '/');

        return [
            'type' => $this->getType($filePath)['type'],
            'path' => $filePath,
            'timestamp' => $stats['time'],
            'size' => $stats['size'],
        ];
    }

    /**
     * @param $domain
     * @return string
     */
    protected function normalizeHost($domain)
    {
        $protocol = Arr::get($this->config, 'protocol', 'http');
        if (0 !== stripos($domain, 'https://') && 0 !== stripos($domain, 'http://')) {
            $domain = $protocol . "://{$domain}";
        }

        return rtrim($domain, '/') . '/';
    }

    public function getUrl($path)
    {
        $domain = Arr::get($this->config, 'domain');
        return $this->normalizeHost($domain).$path;
    }

    /**
     * formatUpYunMetaData
     *
     * @param $metadata
     * @return bool
     */
    protected function formatUpYunMetaData($metadata)
    {
        $originParam = ['x-upyun-file-size', 'x-upyun-file-date'];
        if (gettype($metadata) != 'array') {
            return false;
        }
        foreach ($originParam as $param) {
            if (!array_key_exists($param, $metadata)) {
                return false;
            }
        }
        $newMetaData = $metadata;
        foreach ($originParam as $param) {
            switch ($param) {
                case 'x-upyun-file-size':
                    $newMetaData['size'] = $newMetaData[$param];
                    unset($newMetaData[$param]);
                    break;
                case 'x-upyun-file-date':
                    $newMetaData['timestamp'] = $newMetaData[$param];
                    unset($newMetaData[$param]);
                    break;
                default:
                    break;
            }
        }
        return $newMetaData;
    }
}