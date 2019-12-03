<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Update\Struct\Version;
use Symfony\Component\HttpFoundation\RequestStack;

class PluginCompatibility
{
    public const PLUGIN_COMPATIBILITY_COMPATIBLE = 'compatible';
    public const PLUGIN_COMPATIBILITY_NOT_COMPATIBLE = 'notCompatible';
    public const PLUGIN_COMPATIBILITY_UPDATABLE_NOW = 'updatableNow';
    public const PLUGIN_COMPATIBILITY_UPDATABLE_FUTURE = 'updatableFuture';

    public const PLUGIN_COMPATIBILITY_NOT_IN_STORE = 'notInStore';

    public const PLUGIN_DEACTIVATION_FILTER_ALL = 'all';
    public const PLUGIN_DEACTIVATION_FILTER_NOT_COMPATIBLE = 'notCompatible';
    public const PLUGIN_DEACTIVATION_FILTER_NONE = '';

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepository;

    /**
     * @var StoreClient
     */
    private $storeClient;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(StoreClient $storeClient, EntityRepositoryInterface $pluginRepository, RequestStack $requestStack)
    {
        $this->storeClient = $storeClient;
        $this->pluginRepository = $pluginRepository;
        $this->requestStack = $requestStack;
    }

    public function getPluginCompatibilities(Version $update, Context $context, ?PluginCollection $plugins = null): array
    {
        $currentLanguage = $this->requestStack->getCurrentRequest()->query->get('language', 'en-GB');
        if ($plugins === null) {
            $plugins = $this->fetchActivePlugins($context);
        }
        $storeInfo = $this->storeClient->getPluginCompatibilities($update->version, $currentLanguage, $plugins);
        $storeInfoValues = array_column($storeInfo, 'name');
        $me = $this;

        $pluginInfo = array_map(static function (PluginEntity $entity) use ($storeInfoValues, $storeInfo, $me) {
            $index = array_search($entity->getName(), $storeInfoValues, true);

            if ($index === false) {
                // Plugin not available in store
                return [
                    'name' => $entity->getName(),
                    'managedByComposer' => $entity->getManagedByComposer(),
                    'installedVersion' => $entity->getVersion(),
                    'statusVariant' => 'error',
                    'statusColor' => null,
                    'statusMessage' => '',
                    'statusName' => self::PLUGIN_COMPATIBILITY_NOT_IN_STORE,
                ];
            }

            return array_merge([
                'name' => $entity->getName(),
                'managedByComposer' => $entity->getManagedByComposer(),
                'installedVersion' => $entity->getVersion(),
                'statusMessage' => $storeInfo[$index]['status']['label'],
                'statusName' => $storeInfo[$index]['status']['name'],
            ], $me->mapColorToStatusVariant($storeInfo[$index]['status']['type']));
        }, array_values($plugins->getElements()));

        return $pluginInfo;
    }

    /**
     * @return PluginEntity[]
     */
    public function getPluginsToDeactivate(Version $update, Context $context, string $deactivationFilter = self::PLUGIN_DEACTIVATION_FILTER_NOT_COMPATIBLE): array
    {
        $deactivationFilter = trim($deactivationFilter);

        if ($deactivationFilter === self::PLUGIN_DEACTIVATION_FILTER_NONE) {
            return [];
        }

        $plugins = $this->fetchActivePlugins($context);
        $compatibilities = $this->getPluginCompatibilities($update, $context, $plugins);

        $pluginsToDeactivate = [];

        foreach ($compatibilities as $compatibility) {
            $skip = $compatibility['statusName'] === self::PLUGIN_COMPATIBILITY_COMPATIBLE
                || $compatibility['statusName'] === self::PLUGIN_COMPATIBILITY_NOT_IN_STORE;

            if ($deactivationFilter !== self::PLUGIN_DEACTIVATION_FILTER_ALL && $skip) {
                continue;
            }

            /** @var PluginEntity|null $plugin */
            $plugin = $plugins->filterByProperty('name', $compatibility['name'])->first();

            if ($plugin && $plugin->getActive()) {
                $pluginsToDeactivate[] = $plugin;
            }
        }

        return $pluginsToDeactivate;
    }

    /**
     * @return PluginEntity[]
     */
    public function getPluginsToReactivate(array $deactivatedPlugins, Version $newVersion, Context $context): array
    {
        $plugins = $this->fetchInactivePlugins($deactivatedPlugins, $context);
        $compatibilities = $this->getPluginCompatibilities($newVersion, $context, $plugins);

        $pluginsToReactivate = [];

        foreach ($compatibilities as $compatibility) {
            if ($compatibility['statusName'] !== self::PLUGIN_COMPATIBILITY_COMPATIBLE) {
                continue;
            }

            /** @var PluginEntity|null $plugin */
            $plugin = $plugins->filterByProperty('name', $compatibility['name'])->first();

            if ($plugin && !$plugin->getActive()) {
                $pluginsToReactivate[] = $plugin;
            }
        }

        return $pluginsToReactivate;
    }

    private function fetchActivePlugins(Context $context): PluginCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', 1));

        /** @var PluginCollection $collection */
        $collection = $this->pluginRepository->search($criteria, $context)->getEntities();

        return $collection;
    }

    private function fetchInactivePlugins(array $pluginIds, Context $context): PluginCollection
    {
        $criteria = new Criteria($pluginIds);
        $criteria->addFilter(new EqualsFilter('active', 0));

        /** @var PluginCollection $collection */
        $collection = $this->pluginRepository->search($criteria, $context)->getEntities();

        return $collection;
    }

    private function mapColorToStatusVariant(string $color): array
    {
        switch ($color) {
            case 'green':
                return [
                    'statusColor' => null,
                    'statusVariant' => 'success',
                ];
            case 'red':
                return [
                    'statusColor' => null,
                    'statusVariant' => 'error',
                ];
            default:
                return [
                    'statusColor' => $color,
                    'statusVariant' => null,
                ];
        }
    }
}
