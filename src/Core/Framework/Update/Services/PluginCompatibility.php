<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
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

    /**
     * @var PluginLifecycleService
     */
    private $pluginLifecycleService;

    public function __construct(StoreClient $storeClient, EntityRepositoryInterface $pluginRepository, RequestStack $requestStack, PluginLifecycleService $pluginLifecycleService)
    {
        $this->storeClient = $storeClient;
        $this->pluginRepository = $pluginRepository;
        $this->requestStack = $requestStack;
        $this->pluginLifecycleService = $pluginLifecycleService;
    }

    public function getPluginCompatibilities(Version $version, Context $context): array
    {
        $currentLanguage = $this->requestStack->getCurrentRequest()->query->get('language', 'en-GB');
        $plugins = $this->fetchInstalledPlugins($context);
        $storeInfo = $this->storeClient->getPluginCompatibilities($version->version, $currentLanguage, $plugins);
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

    public function deactivateIncompatiblePlugins(Version $update, Context $context, string $deactivationFilter = self::PLUGIN_DEACTIVATION_FILTER_NOT_COMPATIBLE): void
    {
        $deactivationFilter = trim($deactivationFilter);

        if ($deactivationFilter === self::PLUGIN_DEACTIVATION_FILTER_NONE) {
            return;
        }

        $compatibilities = $this->getPluginCompatibilities($update, $context);
        $plugins = $this->fetchInstalledPlugins($context);

        foreach ($compatibilities as $compatibility) {
            $skip = $compatibility['statusName'] === self::PLUGIN_COMPATIBILITY_COMPATIBLE
                || $compatibility['statusName'] === self::PLUGIN_COMPATIBILITY_NOT_IN_STORE;

            if ($deactivationFilter !== self::PLUGIN_DEACTIVATION_FILTER_ALL && $skip) {
                continue;
            }

            /** @var PluginEntity|null $plugin */
            $plugin = $plugins->filterByProperty('name', $compatibility['name'])->first();
            if ($plugin && $plugin->getActive()) {
                $this->pluginLifecycleService->deactivatePlugin($plugin, $context);
            }
        }
    }

    private function fetchInstalledPlugins(Context $context): PluginCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('installedAt', null)]));

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
