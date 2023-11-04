<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFolders;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @final
 */
#[Package('core')]
class CacheClearer
{
    /**
     * @internal
     *
     * @param CacheItemPoolInterface[] $adapters
     */
    public function __construct(
        private readonly array $adapters,
        private readonly CacheClearerInterface $cacheClearer,
        private readonly Filesystem $filesystem,
        private readonly string $cacheDir,
        private readonly string $environment,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    public function clear(): void
    {
        foreach ($this->adapters as $adapter) {
            $adapter->clear();
        }

        if (!is_writable($this->cacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write in the "%s" directory', $this->cacheDir));
        }

        $this->cacheClearer->clear($this->cacheDir);
        $this->filesystem->remove($this->cacheDir . '/twig');
        $this->cleanupUrlGeneratorCacheFiles();

        $this->cleanupOldContainerCacheDirectories();
    }

    public function clearContainerCache(): void
    {
        $finder = (new Finder())->in($this->cacheDir)->name('*Container*')->depth(0);
        $containerCaches = [];

        foreach ($finder->getIterator() as $containerPaths) {
            $containerCaches[] = $containerPaths->getRealPath();
        }

        $this->filesystem->remove($containerCaches);
    }

    public function scheduleCacheFolderCleanup(): void
    {
        $this->messageBus->dispatch(new CleanupOldCacheFolders());
    }

    /**
     * @param list<string> $keys
     */
    public function deleteItems(array $keys): void
    {
        foreach ($this->adapters as $adapter) {
            $adapter->deleteItems($keys);
        }
    }

    public function prune(): void
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter instanceof PruneableInterface) {
                $adapter->prune();
            }
        }
    }

    public function cleanupOldContainerCacheDirectories(): void
    {
        // Don't delete other folders while paratest is running
        if (EnvironmentHelper::getVariable('TEST_TOKEN')) {
            return;
        }

        $finder = (new Finder())
            ->directories()
            ->name($this->environment . '*')
            ->in(\dirname($this->cacheDir) . '/');

        if (!$finder->hasResults()) {
            return;
        }

        $remove = [];
        foreach ($finder->getIterator() as $directory) {
            if ($directory->getPathname() !== $this->cacheDir) {
                $remove[] = $directory->getPathname();
            }
        }

        if ($remove !== []) {
            $this->filesystem->remove($remove);
        }
    }

    private function cleanupUrlGeneratorCacheFiles(): void
    {
        $finder = (new Finder())
            ->in($this->cacheDir)
            ->files()
            ->name(['UrlGenerator.php', 'UrlGenerator.php.meta']);

        if (!$finder->hasResults()) {
            return;
        }

        $files = iterator_to_array($finder->getIterator());

        if (\count($files) > 0) {
            $this->filesystem->remove(array_map(static fn (\SplFileInfo $file): string => $file->getPathname(), $files));
        }
    }
}
