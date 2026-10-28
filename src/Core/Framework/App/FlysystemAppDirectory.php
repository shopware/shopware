<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Event\PostAppDeletedEvent;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('framework')]
class FlysystemAppDirectory implements AppDirectory, EventSubscriberInterface
{
    final public const SOURCE_TYPE = 'flysystem';
    final public const LEGACY_SOURCE_TYPE = 'private-app';
    final public const DIRECTORY = 'private-apps';

    public function __construct(
        private readonly FilesystemOperator $storage,
        private readonly AppExtractor $appExtractor,
        private readonly TemporaryDirectoryFactory $temporaryDirectoryFactory,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppUpdatedEvent::class => 'onAppUpdated',
            PostAppDeletedEvent::class => 'onAppDeleted',
        ];
    }

    public function store(string $zipPath): void
    {
        $temporaryDirectory = Path::join(
            $this->temporaryDirectoryFactory->path(),
            'app-directory-' . bin2hex(random_bytes(8))
        );

        try {
            $appPath = $this->appExtractor->extract($zipPath, $temporaryDirectory);
            $manifestPath = Path::join($appPath, 'manifest.xml');
            $manifest = Manifest::createFromXmlFile($manifestPath);

            $name = $manifest->getMetadata()->getName();
            $directory = Path::join(self::DIRECTORY, $name, Hasher::hashFile($zipPath));

            $this->storage->createDirectory($directory);
            $this->writeFile(Path::join($directory, 'manifest.xml'), $manifestPath);
            $this->writeFile(Path::join($directory, $name . '.zip'), $zipPath);
        } finally {
            $this->filesystem->remove($temporaryDirectory);
        }
    }

    public function load(): array
    {
        $latestByApp = [];

        foreach ($this->storage->listContents(self::DIRECTORY, true) as $item) {
            if (!$item instanceof FileAttributes || !str_ends_with($item->path(), '.zip')) {
                continue;
            }

            $segments = explode('/', $item->path());
            if (\count($segments) < 3) {
                continue;
            }

            $appName = $segments[1];
            $lastModified = $item->lastModified();

            if (!isset($latestByApp[$appName]) || $latestByApp[$appName]['lastModified'] < $lastModified) {
                $latestByApp[$appName] = [
                    'lastModified' => $lastModified,
                    'path' => $item->path(),
                ];
            }
        }

        $manifests = [];
        foreach ($latestByApp as $app) {
            $manifestPath = Path::join(\dirname($app['path']), 'manifest.xml');

            try {
                $manifest = Manifest::createFromXml($this->storage->read($manifestPath));
            } catch (AppXmlParsingException $exception) {
                $this->logger->error('Manifest XML parsing error. Reason: ' . $exception->getMessage(), ['trace' => $exception->getTrace()]);

                continue;
            }

            $manifest->setPath($app['path']);
            $manifest->setSourceType(self::SOURCE_TYPE);

            $manifests[$manifest->getMetadata()->getName()] = $manifest;
        }

        return $manifests;
    }

    public function delete(string $technicalName): void
    {
        $this->storage->deleteDirectory(Path::join(self::DIRECTORY, $technicalName));
    }

    public function onAppUpdated(AppUpdatedEvent $event): void
    {
        $app = $event->getApp();
        if (!\in_array($app->getSourceType(), [self::SOURCE_TYPE, self::LEGACY_SOURCE_TYPE], true)) {
            return;
        }

        $appDirectory = Path::join(self::DIRECTORY, $app->getName());
        $currentDirectory = $this->currentDirectory($app->getPath(), $appDirectory);

        foreach ($this->storage->listContents($appDirectory) as $item) {
            if (!$item->isDir() || $item->path() === $currentDirectory) {
                continue;
            }

            $this->storage->deleteDirectory($item->path());
        }
    }

    public function onAppDeleted(PostAppDeletedEvent $event): void
    {
        if (!\in_array($event->sourceType, [self::SOURCE_TYPE, self::LEGACY_SOURCE_TYPE], true)) {
            return;
        }

        $this->delete($event->appName);
    }

    private function currentDirectory(string $path, string $appDirectory): string
    {
        $relativePath = Path::isBasePath($appDirectory, $path) ? Path::makeRelative($path, $appDirectory) : '';
        $segments = array_values(array_filter(explode('/', $relativePath)));

        if (\count($segments) < 2) {
            return '';
        }

        return Path::join($appDirectory, $segments[0]);
    }

    private function writeFile(string $destination, string $source): void
    {
        $sourceStream = fopen($source, 'rb');
        if ($sourceStream === false) {
            throw AppException::cannotCreateUploadTemporaryFile($source);
        }

        try {
            $this->storage->writeStream($destination, $sourceStream);
        } finally {
            fclose($sourceStream);
        }
    }
}
