<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;
use Shopware\Core\Framework\Test\Filesystem\Adapter\MemoryAdapterFactory;

/**
 * Use this trait if your test operates with a filesystem
 */
trait FilesystemBehaviour
{
    /**
     * @var Filesystem[]
     */
    private $filesystems;

    public function getFilesystem(string $serviceId)
    {
        if (isset($this->filesystems[$serviceId])) {
            return $this->filesystems[$serviceId];
        }

        $container = KernelLifecycleManager::getKernel()
            ->getContainer();

        if ($container->has('test.service_container')) {
            $container = $container->get('test.service_container');
        }

        $this->filesystems[$serviceId] = $container->get($serviceId);

        return $this->filesystems[$serviceId];
    }

    public function getPublicFilesystem()
    {
        return $this->getFilesystem('shopware.filesystem.public');
    }

    /**
     * @before
     */
    public function assertEmptyInMemoryFilesystem()
    {
        if (!$this->getPublicFilesystem()->getAdapter() instanceof MemoryAdapter) {
            throw new \RuntimeException('The service \'shopware.filesystem.public\' must be configured to use a MemoryAdapter in test environments.');
        }
    }

    /**
     * @after
     */
    public function removeWrittenFilesAfterFilesystemTests(): void
    {
        MemoryAdapterFactory::clearInstancesMemory();
        $this->filesystems = [];
    }
}
