<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;
use Shopware\Core\Framework\Test\Filesystem\Adapter\MemoryAdapterFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Use this trait if your test operates with a filesystem
 */
trait FilesystemBehaviour
{
    public function getFilesystem(string $serviceId): Filesystem
    {
        /** @var Filesystem $filesystem */
        $filesystem = $this->getContainer()->get($serviceId);

        return $filesystem;
    }

    public function getPublicFilesystem(): Filesystem
    {
        return $this->getFilesystem('shopware.filesystem.public');
    }

    /**
     * @before
     */
    public function assertEmptyInMemoryFilesystem(): void
    {
        if (!$this->getPublicFilesystem()->getAdapter() instanceof MemoryAdapter) {
            throw new \RuntimeException('The service \'shopware.filesystem.public\' must be configured to use a MemoryAdapter in test environments.');
        }
    }

    /**
     * @after
     * @before
     */
    public function removeWrittenFilesAfterFilesystemTests(): void
    {
        MemoryAdapterFactory::clearInstancesMemory();
    }

    abstract protected function getContainer(): ContainerInterface;
}
