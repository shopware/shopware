<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Filesystem\Adapter;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Memory\MemoryAdapter;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;

class MemoryAdapterFactory implements AdapterFactoryInterface
{
    /**
     * @var MemoryAdapter[]
     */
    private static $instances;

    public static function clearInstancesMemory(): void
    {
        if (!static::$instances) {
            static::$instances = [];

            return;
        }

        foreach (static::$instances as $memoryAdapter) {
            foreach ($memoryAdapter->listContents() as $content) {
                if ($content['type'] === 'dir') {
                    $memoryAdapter->deleteDir($content['path']);

                    continue;
                }

                if ($content['type'] === 'file') {
                    $memoryAdapter->delete($content['path']);
                }
            }
        }
    }

    public static function resetInstances(): void
    {
        static::clearInstancesMemory();
        static::$instances = [];
    }

    public function create(array $config): AdapterInterface
    {
        $adapter = new MemoryAdapter();
        static::addAdapter($adapter);

        return $adapter;
    }

    public function getType(): string
    {
        return 'memory';
    }

    private static function addAdapter(MemoryAdapter $adapter): void
    {
        if (!static::$instances) {
            static::$instances = [];
        }

        static::$instances[] = $adapter;
    }
}
