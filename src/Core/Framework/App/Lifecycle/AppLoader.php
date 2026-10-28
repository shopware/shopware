<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Composer\ComposerInfoProvider;
use Shopware\Core\Framework\App\AppDirectory;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AppLoader
{
    final public const COMPOSER_TYPE = 'shopware-app';

    public function __construct(
        private readonly AppDirectory $appDirectory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, Manifest>
     */
    public function load(): array
    {
        return [...$this->appDirectory->load(), ...$this->loadFromComposer()];
    }

    public function deleteApp(string $technicalName): void
    {
        $apps = $this->load();

        if (!isset($apps[$technicalName])) {
            return;
        }

        $manifest = $apps[$technicalName];

        if ($manifest->isManagedByComposer()) {
            throw AppException::cannotDeleteManaged($technicalName);
        }

        $this->appDirectory->delete($technicalName);
    }

    /**
     * @return array<string, Manifest>
     */
    private function loadFromComposer(): array
    {
        $manifests = [];

        foreach (ComposerInfoProvider::getComposerPackages(self::COMPOSER_TYPE) as $package) {
            try {
                $manifest = Manifest::createFromXmlFile($package->path . '/manifest.xml');
                $manifest->setManagedByComposer(true);

                $manifests[$manifest->getMetadata()->getName()] = $manifest;
            } catch (AppXmlParsingException $exception) {
                $this->logger->error('Manifest XML parsing error. Reason: ' . $exception->getMessage(), ['trace' => $exception->getTrace()]);
            }
        }

        return $manifests;
    }
}
