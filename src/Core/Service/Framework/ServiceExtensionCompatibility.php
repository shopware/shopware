<?php declare(strict_types=1);

namespace Shopware\Core\Service\Framework;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Update\Services\ExtensionCompatibility;
use Shopware\Core\Framework\Update\Struct\Version;
use Shopware\Core\Service\ServiceRegistryClient;
use Shopware\Core\Service\ServiceRegistryEntry;

/**
 * @internal
 *
 * @phpstan-import-type Compatibility from ExtensionCompatibility
 */
#[Package('core')]
class ServiceExtensionCompatibility extends ExtensionCompatibility
{
    public function __construct(
        private readonly ExtensionCompatibility $inner,
        private readonly ServiceRegistryClient $serviceRegistryClient,
        private readonly AbstractExtensionDataProvider $extensionDataProvider
    ) {
    }

    /**
     * If an app is installed where there is a service with the same name, we mark it as compatible with the new Shopware version
     *
     * @return list<Compatibility>
     */
    public function getExtensionCompatibilities(Version $update, Context $context, ?ExtensionCollection $extensions = null): array
    {
        $compatibilities = $this->inner->getExtensionCompatibilities($update, $context, $extensions);

        $services = $this->serviceRegistryClient->getAll();
        $serviceNames = array_map(fn (ServiceRegistryEntry $entry) => $entry->name, $services);

        foreach ($compatibilities as $key => $compatibility) {
            if (\in_array($compatibility['name'], $serviceNames, true)) {
                // this app is a service
                $compatibilities[$key]['statusName'] = 'updatableFuture';
                $compatibilities[$key]['statusMessage'] = 'With new Shopware version';
                $compatibilities[$key]['statusColor'] = 'yellow';
                $compatibilities[$key]['statusVariant'] = null;
            }
        }

        return array_values($compatibilities);
    }

    /**
     * Decorate the original result and include apps where there is a service with the same name
     *
     * @return ExtensionStruct[]
     */
    public function getExtensionsToDeactivate(Version $update, Context $context, string $deactivationFilter = self::PLUGIN_DEACTIVATION_FILTER_NOT_COMPATIBLE): array
    {
        $extensions = $this->fetchActiveExtensions($context);

        $extensionsToDeactivate = $this->inner->getExtensionsToDeactivate($update, $context, $deactivationFilter);
        $extensionNames = array_map(fn (ExtensionStruct $extension) => $extension->getName(), $extensionsToDeactivate);

        $compatibilities = $this->getExtensionCompatibilities($update, $context);

        foreach ($compatibilities as $compatibility) {
            if ($compatibility['statusName'] === static::PLUGIN_COMPATIBILITY_UPDATABLE_FUTURE && !\in_array($compatibility['name'], $extensionNames, true)) {
                $extension = $extensions->get($compatibility['name']);

                if ($extension && $extension->getActive()) {
                    $extensionsToDeactivate[] = $extension;
                }
            }
        }

        return $extensionsToDeactivate;
    }

    private function fetchActiveExtensions(Context $context): ExtensionCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', 1));

        return $this->extensionDataProvider->getInstalledExtensions($context, false, $criteria);
    }
}
