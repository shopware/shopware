<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Source;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem as AppFilesystem;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('framework')]
readonly class Local implements UploadSource
{
    public function __construct(
        private string $projectRoot,
        private ?AppExtractor $appExtractor = null,
        private ?Io $io = null,
    ) {
    }

    public static function name(): string
    {
        return 'local';
    }

    public function supports(Manifest|AppEntity $app): bool
    {
        if ($app->getSourceType() !== null && $app->getSourceType() !== self::name()) {
            return false;
        }

        return match (true) {
            $app instanceof AppEntity => $app->getSourceType() === $this->name(),
            $app instanceof Manifest => is_dir(\dirname($app->getPath())),
        };
    }

    public function filesystem(AppEntity|Manifest $app): AppFilesystem
    {
        return new AppFilesystem(
            match (true) {
                $app instanceof AppEntity => str_starts_with($app->getPath(), $this->projectRoot)
                        ? $app->getPath()
                        : Path::join($this->projectRoot, $app->getPath()),
                $app instanceof Manifest => $app->getPath(),
            }
        );
    }

    /**
     * @param array<AppFilesystem> $filesystems
     */
    public function reset(array $filesystems): void
    {
    }

    public function storeArchive(string $archivePath, Manifest $manifest): string
    {
        if ($this->appExtractor === null) {
            throw AppException::noSourceSupports();
        }

        return $this->appExtractor->extract($archivePath, Path::join($this->projectRoot, 'custom/apps'), $manifest->getMetadata()->getName());
    }

    public function backup(AppEntity $app): string
    {
        $backupPath = Path::join(sys_get_temp_dir(), 'app-upload-backup-' . bin2hex(random_bytes(8)));
        $this->ioFilesystem()->mirror($this->pathForApp($app), $backupPath);

        return $backupPath;
    }

    public function restore(AppEntity $app, string $backupPath): void
    {
        $path = $this->pathForApp($app);
        $this->ioFilesystem()->remove($path);
        $this->ioFilesystem()->mirror($backupPath, $path);
    }

    public function remove(AppEntity $app): void
    {
        $this->ioFilesystem()->remove($this->pathForApp($app));
    }

    public function removeByManifest(Manifest $manifest): void
    {
        $this->ioFilesystem()->remove($manifest->getPath());
    }

    private function pathForApp(AppEntity $app): string
    {
        return str_starts_with($app->getPath(), $this->projectRoot)
            ? $app->getPath()
            : Path::makeAbsolute($app->getPath(), $this->projectRoot);
    }

    private function ioFilesystem(): Io
    {
        return $this->io ?? new Io();
    }
}
