<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Source;

use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppDownloader;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\FlysystemAppDirectory;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('framework')]
class FlysystemSource implements Source
{
    public function __construct(
        private readonly FilesystemOperator $storage,
        private readonly TemporaryDirectoryFactory $temporaryDirectoryFactory,
        private readonly Io $io,
        private readonly AppDownloader $appDownloader,
        private readonly AppExtractor $appExtractor,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function name(): string
    {
        return FlysystemAppDirectory::SOURCE_TYPE;
    }

    public function supports(Manifest|AppEntity $app): bool
    {
        return \in_array($app->getSourceType(), [FlysystemAppDirectory::SOURCE_TYPE, FlysystemAppDirectory::LEGACY_SOURCE_TYPE], true);
    }

    public function filesystem(Manifest|AppEntity $app): Filesystem
    {
        $version = $app instanceof Manifest ? $app->getMetadata()->getVersion() : $app->getVersion();
        $name = $app instanceof Manifest ? $app->getMetadata()->getName() : $app->getName();

        return new Filesystem(
            $this->downloadAppZip($app->getPath(), $name, $version)
        );
    }

    /**
     * @param array<Filesystem> $filesystems
     */
    public function reset(array $filesystems): void
    {
        $this->io->remove(
            array_map(static fn (Filesystem $fs) => $fs->location, $filesystems)
        );
    }

    private function downloadAppZip(string $appPath, string $appName, string $appVersion): string
    {
        $temporaryPath = Path::join(
            $this->temporaryDirectoryFactory->path(),
            Hasher::hash($appPath),
            $appVersion
        );

        $localZipLocation = Path::join($temporaryPath, $appName . '.zip');

        $this->logger->info(\sprintf('Downloading app "%s" to "%s"', $appName, $temporaryPath));

        try {
            $this->appDownloader->downloadFromFilesystem($this->storage, $appPath, $localZipLocation);
            $this->appExtractor->extract($localZipLocation, $temporaryPath, $appName);
            $this->io->remove($localZipLocation);
        } catch (HttpException $exception) {
            throw AppException::cannotMountAppFilesystem($appName, $exception);
        }

        return Path::join($temporaryPath, $appName);
    }
}
