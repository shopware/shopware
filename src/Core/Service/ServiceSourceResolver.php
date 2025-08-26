<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Exception\AppArchiveValidationFailure;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\Source;
use Shopware\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Service\ServiceRegistry\Client;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 *
 * @phpstan-type ServiceSourceConfig array{app-version: string, app-hash: string, app-revision: string, app-zip-url: string, app-hash-algorithm: string, app-min-shop-supported-version: string}
 */
#[Package('framework')]
class ServiceSourceResolver implements Source
{
    public function __construct(
        private readonly Client $client,
        private readonly TemporaryDirectoryFactory $temporaryDirectoryFactory,
        private readonly AppExtractor $appExtractor,
        private readonly Io $io
    ) {
    }

    public static function name(): string
    {
        return 'service';
    }

    public function filesystemForVersion(AppInfo $appInfo): Filesystem
    {
        return new Filesystem($this->downloadVersion($appInfo->name, $appInfo->zipUrl));
    }

    public function supports(Manifest|AppEntity $app): bool
    {
        return match (true) {
            $app instanceof AppEntity => $app->getSourceType() === $this->name(),
            $app instanceof Manifest => preg_match('#^https?://#', $app->getPath()) && $app->getMetadata()->isSelfManaged(),
        };
    }

    public function filesystem(Manifest|AppEntity $app): Filesystem
    {
        $temporaryDirectory = $this->temporaryDirectoryFactory->path();

        $name = $app instanceof Manifest ? $app->getMetadata()->getName() : $app->getName();

        // app is already on the filesystem, use that
        $appPath = Path::join($temporaryDirectory, $name);
        if ($this->io->exists($appPath)) {
            return new Filesystem($appPath);
        }

        /** @var ServiceSourceConfig $sourceConfig */
        $sourceConfig = $app->getSourceConfig();
        $appInfo = AppInfo::fromNameAndArray($name, $sourceConfig);

        return $this->filesystemForVersion($appInfo);
    }

    public function reset(array $filesystems): void
    {
    }

    private function downloadVersion(
        string $serviceName,
        string $zipUrl,
    ): string {
        $destination = Path::join($this->temporaryDirectoryFactory->path(), $serviceName);
        $localZipLocation = Path::join($destination, $serviceName . '.zip');
        $this->io->mkdir($destination);

        try {
            $zipData = $this->client->fetchServiceZipVersion($zipUrl);
            foreach ($zipData as $chunk) {
                try {
                    $this->io->appendToFile($localZipLocation, $chunk->getContent());
                } catch (\Exception $e) {
                    throw ServiceException::cannotWriteAppToDestination($destination);
                }
            }
        } catch (ServiceException $e) {
            throw AppException::cannotMountAppFilesystem($serviceName, $e); // @phpstan-ignore shopware.domainException
        }

        try {
            $this->appExtractor->extract(
                $localZipLocation,
                $this->temporaryDirectoryFactory->path(),
                $serviceName,
            );
        } catch (PluginException|AppArchiveValidationFailure $e) {
            throw AppException::cannotMountAppFilesystem($serviceName, $e); // @phpstan-ignore shopware.domainException
        } finally {
            $this->io->remove($localZipLocation);
        }

        return $destination;
    }
}
