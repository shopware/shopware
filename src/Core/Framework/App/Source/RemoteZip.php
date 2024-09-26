<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Source;

use Shopware\Core\Framework\App\AppDownloader;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('core')]
readonly class RemoteZip implements Source
{
    public function __construct(
        private AbstractTemporaryDirectoryFactory $temporaryDirectoryFactory,
        private AppDownloader $downloader,
        private AppExtractor $appExtractor,
        private Io $io = new Io()
    ) {
    }

    public static function name(): string
    {
        return 'remote-zip';
    }

    public function supports(Manifest|AppEntity $app): bool
    {
        return match (true) {
            $app instanceof AppEntity => $app->getSourceType() === $this->name(),
            $app instanceof Manifest => (bool) preg_match('#^https?://#', $app->getPath()),
        };
    }

    public function filesystem(Manifest|AppEntity $app): Filesystem
    {
        $temporaryDirectory = $this->temporaryDirectoryFactory->path();

        if ($app instanceof AppEntity && $this->io->exists(Path::join($temporaryDirectory, $app->getName()))) {
            // app is already on the filesystem
            return new Filesystem(Path::join($temporaryDirectory, $app->getName()));
        }

        // if it's a Manifest instance, we just download it again (could be new version)
        return new Filesystem(
            match (true) {
                $app instanceof AppEntity => $this->downloadAppZip($app->getPath(), $app->getName()),
                $app instanceof Manifest => $this->downloadAppZip($app->getPath(), $app->getMetadata()->getName()),
            }
        );
    }

    /**
     * @param array<Filesystem> $filesystems
     */
    public function reset(array $filesystems): void
    {
        $this->io->remove(
            array_map(fn (Filesystem $fs) => $fs->location, $filesystems)
        );
    }

    private function downloadAppZip(string $remoteZipLocation, string $appName): string
    {
        $directory = $this->temporaryDirectoryFactory->path();

        $appPath = Path::join($directory, $appName);
        $localZipLocation = $appPath . '.zip';

        try {
            $this->downloader->download($remoteZipLocation, $localZipLocation);
            $this->appExtractor->extract($appName, $localZipLocation, $appPath);
        } catch (HttpException $e) {
            throw AppException::cannotMountAppFilesystem($appName, $e);
        }

        return $appPath;
    }
}
