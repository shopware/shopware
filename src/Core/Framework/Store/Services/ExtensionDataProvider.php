<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;

/**
 * @internal
 */
#[Package('checkout')]
class ExtensionDataProvider extends AbstractExtensionDataProvider
{
    final public const HEADER_NAME_TOTAL_COUNT = 'SW-Meta-Total';

    public function __construct(
        private readonly ExtensionLoader $extensionLoader,
        private readonly EntityRepository $appRepository,
        private readonly EntityRepository $pluginRepository,
        private readonly ExtensionListingLoader $extensionListingLoader
    ) {
    }

    public function getInstalledExtensions(Context $context, bool $loadCloudExtensions = true, ?Criteria $searchCriteria = null): ExtensionCollection
    {
        $appCriteria = $searchCriteria ? clone $searchCriteria : new Criteria();
        $appCriteria->addAssociation('translations');
        $appCriteria->addFilter(new EqualsFilter('selfManaged', false));

        /** @var AppCollection $installedApps */
        $installedApps = $this->appRepository->search($appCriteria, $context)->getEntities();

        $pluginCriteria = $searchCriteria ? clone $searchCriteria : new Criteria();
        $pluginCriteria->addAssociation('translations');

        /** @var PluginCollection $installedPlugins */
        $installedPlugins = $this->pluginRepository->search($pluginCriteria, $context)->getEntities();
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

        if (!$app instanceof AppEntity) {
            throw StoreException::extensionNotFoundFromTechnicalName($technicalName);
        }

        return $app;
    }

    public function getAppEntityFromId(string $id, Context $context): AppEntity
    {
        $criteria = new Criteria([$id]);
        $app = $this->appRepository->search($criteria, $context)->getEntities()->first();

        if (!$app instanceof AppEntity) {
            throw StoreException::extensionNotFoundFromId($id);
        }

        return $app;
    }

    protected function getDecorated(): AbstractExtensionDataProvider
    {
        throw new DecorationPatternException(self::class);
    }
}
