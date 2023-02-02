<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Filesystem\Adapter;

use League\Flysystem\FilesystemAdapter;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use Shopware\Core\Framework\Adapter\Filesystem\MemoryFilesystemAdapter;

/**
 * @internal
 */
class MemoryAdapterFactory implements AdapterFactoryInterface
{
    /**
     * @var MemoryFilesystemAdapter[]
     */
    private static ?array $instances = null;

    public static function clearInstancesMemory(): void
    {
        if (!static::$instances) {
            static::$instances = [];

            return;
        }

        foreach (static::$instances as $memoryAdapter) {
            $memoryAdapter->deleteEverything();
        }
    }

    public static function resetInstances(): void
    {
        static::clearInstancesMemory();
        static::$instances = [];
    }

    public function create(array $config): FilesystemAdapter
    {
        $adapter = new MemoryFilesystemAdapter();
        static::addAdapter($adapter);

        return $adapter;
    }

    public function getType(): string
    {
        return 'memory';
    }

    private static function addAdapter(MemoryFilesystemAdapter $adapter): void
    {
        if (!static::$instances) {
            static::$instances = [];
        }

        static::$instances[] = $adapter;
    }
}
