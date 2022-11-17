<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFolders;
use Shopware\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFoldersHandler;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:becomes-final - will be final starting with v6.5.0.0 and won't extend AbstractMessageHandler anymore
 */
class CacheClearer extends AbstractMessageHandler
{
    /**
     * @deprecated tag:v6.5.0 - reason:becomes-private - will be private and natively typed starting with v6.5.0.0
     *
     * @var CacheClearerInterface
     */
    protected $cacheClearer;

    /**
     * @deprecated tag:v6.5.0 - reason:becomes-private - will be private and natively typed starting with v6.5.0.0
     *
     * @var string
     */
    protected $cacheDir;

    /**
     * @deprecated tag:v6.5.0 - reason:becomes-private - will be private and natively typed starting with v6.5.0.0
     *
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @deprecated tag:v6.5.0 - reason:becomes-private - will be private and natively typed starting with v6.5.0.0
     *
     * @var CacheItemPoolInterface[]
     */
    protected $adapters;

    /**
     * @deprecated tag:v6.5.0 - reason:becomes-private - will be private and natively typed starting with v6.5.0.0
     *
     * @var string
     */
    protected $environment;

    private MessageBusInterface $messageBus;

    /**
     * @internal
     *
     * @param CacheItemPoolInterface[] $adapters
     */
    public function __construct(
        array $adapters,
        CacheClearerInterface $cacheClearer,
        Filesystem $filesystem,
        string $cacheDir,
        string $environment,
        MessageBusInterface $messageBus
    ) {
        $this->adapters = $adapters;
        $this->cacheClearer = $cacheClearer;
        $this->cacheDir = $cacheDir;
        $this->filesystem = $filesystem;
        $this->environment = $environment;
        $this->messageBus = $messageBus;
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

    /**
     * @param object $message
     *
     * @deprecated tag:v6.5.0 - will be removed, used `CleanUpOldCacheFoldersHandler` instead
     */
    public function handle($message): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', CleanupOldCacheFoldersHandler::class)
        );

        $this->cleanupOldContainerCacheDirectories();
    }

    /**
     * @deprecated tag:v6.5.0 - reason:remove-subscriber - will be removed, used `CleanUpOldCacheFoldersHandler` instead
     *
     * @return iterable<string>
     */
    public static function getHandledMessages(): iterable
    {
        return [];
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
            $this->filesystem->remove(array_map(static function (\SplFileInfo $file): string {
                return $file->getPathname();
            }, $files));
        }
    }
}
