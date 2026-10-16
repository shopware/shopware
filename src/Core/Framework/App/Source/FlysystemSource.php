<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Source;

use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppDownloader;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Event\PostAppDeletedEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('framework')]
class FlysystemSource implements NamedSource, UploadSource, EventSubscriberInterface
{
    public function __construct(
        protected readonly FilesystemOperator $storage,
        protected readonly TemporaryDirectoryFactory $temporaryDirectoryFactory,
        protected readonly Io $io,
        protected readonly AppDownloader $appDownloader,
        protected readonly AppExtractor $appExtractor,
        protected readonly LoggerInterface $logger,
        private readonly string $sourceType = 'flysystem',
        protected readonly string $storagePrefix = 'apps',
    ) {
    }

    public static function name(): string
    {
        return 'flysystem';
    }

    public function sourceType(): string
    {
        return $this->sourceType;
    }

    public static function getSubscribedEvents(): array
    {
        return [PostAppDeletedEvent::class => 'onAppDeleted'];
    }

    public function onAppDeleted(PostAppDeletedEvent $event): void
    {
        if ($event->sourceType === $this->sourceType) {
            $this->storage->deleteDirectory($this->appDirectory($event->appName));
        }
    }

    public function supports(Manifest|AppEntity $app): bool
    {
        return $app->getSourceType() === $this->sourceType;
    }

    public function filesystem(Manifest|AppEntity $app): Filesystem
    {
        $version = $app instanceof Manifest ? $app->getMetadata()->getVersion() : $app->getVersion();
        $name = $app instanceof Manifest ? $app->getMetadata()->getName() : $app->getName();
        $zipPath = $app->getPath();
        $temporaryPath = Path::join(
            $this->temporaryDirectoryFactory->path(),
            Hasher::hash($zipPath),
            $version
        );
        $localZipPath = Path::join($temporaryPath, $name . '.zip');

        $this->logger->info(\sprintf('Downloading app "%s" to "%s"', $name, $temporaryPath));

        try {
            $this->appDownloader->downloadFromFilesystem($this->storage, $zipPath, $localZipPath);
            $this->appExtractor->extract($localZipPath, $temporaryPath, $name);
            $this->io->remove($localZipPath);
        } catch (HttpException $exception) {
            throw AppException::cannotMountAppFilesystem($name, $exception);
        }

        return new Filesystem(Path::join($temporaryPath, $name));
    }

    public function reset(array $filesystems): void
    {
        $this->io->remove(array_map(static fn (Filesystem $filesystem) => $filesystem->location, $filesystems));
    }

    public function storeArchive(string $archivePath, Manifest $manifest): string
    {
        $name = $manifest->getMetadata()->getName();
        $directory = $this->appDirectory($name);
        $this->storage->createDirectory($directory);

        $manifestStream = fopen(Path::join($manifest->getPath(), 'manifest.xml'), 'rb');
        $archiveStream = fopen($archivePath, 'rb');
        if ($manifestStream === false || $archiveStream === false) {
            if (\is_resource($manifestStream)) {
                fclose($manifestStream);
            }
            if (\is_resource($archiveStream)) {
                fclose($archiveStream);
            }

            throw AppException::cannotCreateUploadTemporaryFile($archivePath);
        }

        try {
            $this->storage->writeStream(Path::join($directory, 'manifest.xml'), $manifestStream);
            $zipPath = Path::join($directory, $name . '.zip');
            $this->storage->writeStream($zipPath, $archiveStream);

            return $zipPath;
        } finally {
            fclose($manifestStream);
            fclose($archiveStream);
        }
    }

    public function backup(AppEntity $app): string
    {
        $backupPath = Path::join($this->temporaryDirectoryFactory->path(), 'app-backup-' . bin2hex(random_bytes(8)));
        $this->io->mkdir($backupPath);
        foreach (['manifest.xml', $app->getName() . '.zip'] as $file) {
            $stream = $this->storage->readStream(Path::join($this->appDirectory($app->getName()), $file));
            $localStream = fopen(Path::join($backupPath, $file), 'wb');
            if ($localStream === false) {
                fclose($stream);
                throw AppException::cannotCreateUploadTemporaryFile($backupPath);
            }
            stream_copy_to_stream($stream, $localStream);
            fclose($stream);
            fclose($localStream);
        }

        return $backupPath;
    }

    public function restore(AppEntity $app, string $backupPath): void
    {
        foreach (['manifest.xml', $app->getName() . '.zip'] as $file) {
            $stream = fopen(Path::join($backupPath, $file), 'rb');
            if ($stream === false) {
                throw AppException::cannotCreateUploadTemporaryFile($backupPath);
            }

            try {
                $this->storage->writeStream(Path::join($this->appDirectory($app->getName()), $file), $stream);
            } finally {
                fclose($stream);
            }
        }
    }

    public function remove(AppEntity $app): void
    {
        $this->storage->deleteDirectory($this->appDirectory($app->getName()));
    }

    public function removeByManifest(Manifest $manifest): void
    {
        $this->storage->deleteDirectory($this->appDirectory($manifest->getMetadata()->getName()));
    }

    protected function appDirectory(string $name): string
    {
        return Path::join($this->storagePrefix, $name);
    }
}
