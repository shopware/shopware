<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Update\Struct\Version;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @phpstan-type Compatibility array{name: string, managedByComposer: bool, installedVersion: ?string, statusVariant: ?string, statusColor: ?string, statusMessage: string, statusName: string}
 */
#[Package('system-settings')]
class ExtensionCompatibility
{
    final public const PLUGIN_COMPATIBILITY_COMPATIBLE = 'compatible';
    final public const PLUGIN_COMPATIBILITY_NOT_COMPATIBLE = 'notCompatible';
    final public const PLUGIN_COMPATIBILITY_UPDATABLE_NOW = 'updatableNow';
    final public const PLUGIN_COMPATIBILITY_UPDATABLE_FUTURE = 'updatableFuture';

    final public const PLUGIN_COMPATIBILITY_NOT_IN_STORE = 'notInStore';

    final public const PLUGIN_DEACTIVATION_FILTER_ALL = 'all';
    final public const PLUGIN_DEACTIVATION_FILTER_NOT_COMPATIBLE = 'notCompatible';
    final public const PLUGIN_DEACTIVATION_FILTER_NONE = '';

    /**
     * @internal
     */
    public function __construct(
        private StoreClient $storeClient,
        private AbstractExtensionDataProvider $extensionDataProvider
    ) {
    }

    /**
     * @return list<Compatibility>
     */
    public function getExtensionCompatibilities(Version $update, Context $context, ?ExtensionCollection $extensions = null): array
    {
        if ($extensions === null) {
            $extensions = $this->fetchActiveExtensions($context);
        }

        try {
            $storeInfo = $this->storeClient->getExtensionCompatibilities($context, $update->version, $extensions);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === Response::HTTP_BAD_REQUEST) {
                $storeInfo = [];
            } else {
                throw $e;
            }
        }

        $storeInfoValues = array_column($storeInfo, 'name');

        return array_map(static function (ExtensionStruct $entity) use ($storeInfoValues, $storeInfo) {
            $index = array_search($entity->getName(), $storeInfoValues, true);

            if ($index === false) {
                // Extension not available in store
                return [
                    'name' => $entity->getName(),
                    'managedByComposer' => false,
                    'installedVersion' => $entity->getVersion(),
                    'statusVariant' => 'error',
                    'statusColor' => null,
                    'statusMessage' => '',
                    'statusName' => self::PLUGIN_COMPATIBILITY_NOT_IN_STORE,
                ];
            }

            return array_merge([
                'name' => $entity->getName(),
                'managedByComposer' => false,
                'installedVersion' => $entity->getVersion(),
                'statusMessage' => $storeInfo[$index]['status']['label'],
                'statusName' => $storeInfo[$index]['status']['name'],
            ], self::mapColorToStatusVariant($storeInfo[$index]['status']['type']));
        }, array_values($extensions->getElements()));
    }

    /**
     * @return ExtensionStruct[]
     */
    public function getExtensionsToDeactivate(Version $update, Context $context, string $deactivationFilter = self::PLUGIN_DEACTIVATION_FILTER_NOT_COMPATIBLE): array
    {
        $deactivationFilter = trim($deactivationFilter);

        if ($deactivationFilter === self::PLUGIN_DEACTIVATION_FILTER_NONE) {
            return [];
        }

        /* var ExtensionCollection $extensions */
        $extensions = $this->fetchActiveExtensions($context);
        $compatibilities = $this->getExtensionCompatibilities($update, $context, $extensions);

        $extensionsToDeactivate = [];

        foreach ($compatibilities as $compatibility) {
            $skip = $compatibility['statusName'] === self::PLUGIN_COMPATIBILITY_COMPATIBLE
                || $compatibility['statusName'] === self::PLUGIN_COMPATIBILITY_NOT_IN_STORE;

            if ($deactivationFilter !== self::PLUGIN_DEACTIVATION_FILTER_ALL && $skip) {
                continue;
            }

            $extension = $extensions->get($compatibility['name']);

            if ($extension && $extension->getActive()) {
                $extensionsToDeactivate[] = $extension;
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

    /**
     * @return array{statusColor: ?string, statusVariant: ?string}
     */
    private static function mapColorToStatusVariant(string $color): array
    {
        return match ($color) {
            'green' => [
                'statusColor' => null,
                'statusVariant' => 'success',
            ],
            'red' => [
                'statusColor' => null,
                'statusVariant' => 'error',
            ],
            default => [
                'statusColor' => $color,
                'statusVariant' => null,
            ],
        };
    }
}
