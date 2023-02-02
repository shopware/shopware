<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;

/**
 * @internal
 */
class ExtensionDataProvider extends AbstractExtensionDataProvider
{
    public const HEADER_NAME_TOTAL_COUNT = 'SW-Meta-Total';

    private ExtensionLoader $extensionLoader;

    private EntityRepositoryInterface $appRepository;

    private EntityRepositoryInterface $pluginRepository;

    private ExtensionListingLoader $extensionListingLoader;

    public function __construct(
        ExtensionLoader $extensionLoader,
        EntityRepositoryInterface $appRepository,
        EntityRepositoryInterface $pluginRepository,
        ExtensionListingLoader $extensionListingLoader
    ) {
        $this->extensionLoader = $extensionLoader;
        $this->appRepository = $appRepository;
        $this->pluginRepository = $pluginRepository;
        $this->extensionListingLoader = $extensionListingLoader;
    }

    public function getInstalledExtensions(Context $context, bool $loadCloudExtensions = true, ?Criteria $searchCriteria = null): ExtensionCollection
    {
        $criteria = $searchCriteria ? $searchCriteria : new Criteria();
        $criteria->addAssociation('translations');

        /** @var AppCollection $installedApps */
        $installedApps = $this->appRepository->search($criteria, $context)->getEntities();

        /** @var PluginCollection $installedPlugins */
        $installedPlugins = $this->pluginRepository->search($criteria, $context)->getEntities();
        $pluginCollection = $this->extensionLoader->loadFromPluginCollection($context, $installedPlugins);

        $localExtensions = $this->extensionLoader->loadFromAppCollection($context, $installedApps)->merge($pluginCollection);

        if ($loadCloudExtensions) {
            return $this->extensionListingLoader->load($localExtensions, $context);
        }

        return $localExtensions;
    }

    public function getAppEntityFromTechnicalName(string $technicalName, Context $context): AppEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('name', $technicalName));
        $app = $this->appRepository->search($criteria, $context)->getEntities()->first();

        if ($app === null) {
            throw ExtensionNotFoundException::fromTechnicalName($technicalName);
        }

        return $app;
    }

    public function getAppEntityFromId(string $id, Context $context): AppEntity
    {
        $criteria = new Criteria([$id]);
        $app = $this->appRepository->search($criteria, $context)->getEntities()->first();

        if ($app === null) {
            throw ExtensionNotFoundException::fromId($id);
        }

        return $app;
    }

    protected function getDecorated(): AbstractExtensionDataProvider
    {
        throw new DecorationPatternException(self::class);
    }
}
