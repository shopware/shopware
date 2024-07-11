<?php declare(strict_types=1);

namespace Shopware\Core\Services;

use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Exception\AppArchiveValidationFailure;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\AbstractTemporaryDirectoryFactory;
use Shopware\Core\Framework\App\Source\Source;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Services\Event\ServiceOutdatedEvent;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 *
 * @phpstan-type ServiceSourceConfig array{version: string, hash: string, revision: string, zip-url: string}
 */
#[Package('core')]
class ServiceSourceResolver implements Source
{
    public function __construct(
        private readonly AbstractTemporaryDirectoryFactory $temporaryDirectoryFactory,
        private readonly ServiceClientFactory $serviceClientFactory,
        private readonly AppExtractor $appExtractor,
        private readonly Io $io,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public static function name(): string
    {
        return 'service';
    }

    public function filesystemForVersion(AppInfo $appInfo): Filesystem
    {
        return new Filesystem($this->downloadVersion(
            $this->serviceClientFactory->fromName($appInfo->name),
            $appInfo->name,
            $appInfo->zipUrl
        ));
    }

    public function supports(Manifest|AppEntity $app): bool
    {
        return match (true) {
            $app instanceof AppEntity => $app->getSourceType() === $this->name(),
            $app instanceof Manifest => preg_match('#^https?://#', $app->getPath()) && $app->getMetadata()->isSelfManaged()
        };
    }

    public function filesystem(Manifest|AppEntity $app): Filesystem
    {
        $temporaryDirectory = $this->temporaryDirectoryFactory->path();

        $name = $app instanceof Manifest ? $app->getMetadata()->getName() : $app->getName();

        // app is already on the filesystem, use that
        if ($this->io->exists(Path::join($temporaryDirectory, $name))) {
            return new Filesystem(Path::join($temporaryDirectory, $name));
        }

        /** @var ServiceSourceConfig $sourceConfig */
        $sourceConfig = $app->getSourceConfig();

        return new Filesystem($this->checkVersionAndDownloadAppZip($name, $sourceConfig));
    }

    /**
     * @param ServiceSourceConfig $sourceConfig
     */
    private function checkVersionAndDownloadAppZip(string $serviceName, array $sourceConfig): string
    {
        $client = $this->serviceClientFactory->fromName($serviceName);

        $latestAppInfo = $client->latestAppInfo();

        if (!$this->isLatestVersionInstalled($latestAppInfo, $sourceConfig)) {
            // the app revision has changed in the service, so we must update the app
            // this can happen if the system attempts to download the app, before a service update rollout has completed
            $this->eventDispatcher->dispatch(new ServiceOutdatedEvent($serviceName, Context::createDefaultContext()));

            // the update process will download and extract the app, so we can assume it's present on the FS now
            return Path::join($this->temporaryDirectoryFactory->path(), $serviceName);
        }

        return $this->downloadVersion($client, $serviceName, $sourceConfig['zip-url']);
    }

    private function downloadVersion(
        ServiceClient $client,
        string $serviceName,
        string $zipUrl,
    ): string {
        $destination = Path::join($this->temporaryDirectoryFactory->path(), $serviceName);
        $localZipLocation = Path::join($destination, $serviceName . '.zip');

        $this->io->mkdir($destination);

        try {
            $client->downloadAppZipForVersion($zipUrl, $localZipLocation);
        } catch (ServicesException $e) {
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

    /**
     * @param array{revision: string} $sourceConfig
     */
    private function isLatestVersionInstalled(AppInfo $latestAppInfo, array $sourceConfig): bool
    {
        return $latestAppInfo->revision === $sourceConfig['revision'];
    }

    public function reset(array $filesystems): void
    {
    }
}
