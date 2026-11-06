<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Event\AppUploadValidationEvent;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class LocalAppDirectory implements AppDirectory
{
    public function __construct(
        private readonly string $appDir,
        private readonly LoggerInterface $logger,
        private readonly AppExtractor $appExtractor,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function store(string $zipPath): AppMeta
    {
        $this->eventDispatcher->dispatch(new AppUploadValidationEvent($zipPath));

        $temporaryDirectory = Path::join($this->appDir, '.upload-' . bin2hex(random_bytes(8)));
        $backup = Path::join($temporaryDirectory, 'backup');
        $stored = false;

        try {
            $appPath = $this->appExtractor->extract($zipPath, Path::join($temporaryDirectory, 'extracted'));
            $metadata = $this->appExtractor->getMetadata($appPath);
            $destination = Path::join($this->appDir, basename($appPath));

            if ($this->filesystem->exists($destination)) {
                $this->filesystem->rename($destination, $backup);
            }

            try {
                $this->filesystem->rename($appPath, $destination);
            } catch (\Throwable $exception) {
                if ($this->filesystem->exists($backup)) {
                    $this->filesystem->rename($backup, $destination);
                }

                throw $exception;
            }

            $stored = true;

            return $metadata;
        } finally {
            // Keep the backup available for recovery if restoring the old directory also failed.
            if ($stored || !$this->filesystem->exists($backup)) {
                $this->filesystem->remove($temporaryDirectory);
            }
        }
    }

    /**
     * @codeCoverageIgnore Local apps only store the current version.
     */
    public function removeOldVersions(Manifest $manifest): void
    {
    }

    public function load(): array
    {
        if (!\is_dir($this->appDir)) {
            return [];
        }

        $finder = new Finder();
        $finder->in($this->appDir)
            ->depth('<= 1') // only use manifest files in-app root folders
            ->followLinks()
            ->name('manifest.xml');

        $manifests = [];
        foreach ($finder->files() as $xml) {
            try {
                $manifest = Manifest::createFromXmlFile($xml->getPathname());

                $manifests[$manifest->getMetadata()->getName()] = $manifest;
            } catch (AppXmlParsingException $exception) {
                $this->logger->error('Manifest XML parsing error. Reason: ' . $exception->getMessage(), ['trace' => $exception->getTrace()]);
            }
        }

        // Overriding with local manifests
        $finder = new Finder();

        $finder->in($this->appDir)
            ->depth('<= 1') // only use manifest files in-app root folders
            ->followLinks()
            ->name('manifest.local.xml');

        foreach ($finder->files() as $xml) {
            try {
                $manifest = Manifest::createFromXmlFile($xml->getPathname());

                $manifests[$manifest->getMetadata()->getName()] = $manifest;
            } catch (AppXmlParsingException $exception) {
                $this->logger->error('Local manifest XML parsing error. Reason: ' . $exception->getMessage(), ['trace' => $exception->getTrace()]);
            }
        }

        return $manifests;
    }

    public function delete(string $technicalName): void
    {
        $apps = $this->load();

        if (!isset($apps[$technicalName])) {
            return;
        }

        $this->filesystem->remove($apps[$technicalName]->getPath());
    }
}
