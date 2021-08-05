<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlRoute;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Shopware\Core\Content\LandingPage\LandingPageEvents;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SeoUrlUpdateListener implements EventSubscriberInterface
{
    public const CATEGORY_SEO_URL_UPDATER = 'category.seo-url';
    public const PRODUCT_SEO_URL_UPDATER = 'product.seo-url';
    public const LANDING_PAGE_SEO_URL_UPDATER = 'landing_page.seo-url';

    /**
     * @var SeoUrlUpdater
     */
    private $seoUrlUpdater;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityIndexerRegistry
     */
    private $indexerRegistry;

    public function __construct(SeoUrlUpdater $seoUrlUpdater, Connection $connection, EntityIndexerRegistry $indexerRegistry)
    {
        $this->seoUrlUpdater = $seoUrlUpdater;
        $this->connection = $connection;
        $this->indexerRegistry = $indexerRegistry;
    }

    public function detectSalesChannelEntryPoints(EntityWrittenContainerEvent $event): void
    {
        $properties = ['navigationCategoryId', 'footerCategoryId', 'serviceCategoryId'];

        $salesChannelIds = $event->getPrimaryKeysWithPropertyChange(SalesChannelDefinition::ENTITY_NAME, $properties);

        if (empty($salesChannelIds)) {
            return;
        }

        $this->indexerRegistry->sendIndexingMessage(['category.indexer', 'product.indexer']);
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductEvents::PRODUCT_INDEXER_EVENT => 'updateProductUrls',
            CategoryEvents::CATEGORY_INDEXER_EVENT => 'updateCategoryUrls',
            LandingPageEvents::LANDING_PAGE_INDEXER_EVENT => 'updateLandingPageUrls',
            EntityWrittenContainerEvent::class => 'detectSalesChannelEntryPoints',
        ];
    }

    public function updateCategoryUrls(CategoryIndexerEvent $event): void
    {
        if (\in_array(self::CATEGORY_SEO_URL_UPDATER, $event->getSkip(), true)) {
            return;
        }

        $ids = array_merge($event->getIds(), $this->getCategoryChildren($event->getIds()));

        $this->seoUrlUpdater->update(NavigationPageSeoUrlRoute::ROUTE_NAME, $ids);
    }

    public function updateProductUrls(ProductIndexerEvent $event): void
    {
        if (\in_array(self::PRODUCT_SEO_URL_UPDATER, $event->getSkip(), true)) {
            return;
        }

        $ids = array_merge($event->getIds(), $this->getProductChildren($event->getIds()));

        $this->seoUrlUpdater->update(ProductPageSeoUrlRoute::ROUTE_NAME, $ids);
    }

    public function updateLandingPageUrls(LandingPageIndexerEvent $event): void
    {
        if (\in_array(self::LANDING_PAGE_SEO_URL_UPDATER, $event->getSkip(), true)) {
            return;
        }

        $this->seoUrlUpdater->update(LandingPageSeoUrlRoute::ROUTE_NAME, $event->getIds());
    }

    private function getProductChildren(array $ids): array
    {
        $childrenIds = $this->connection->fetchAll(
            'SELECT DISTINCT LOWER(HEX(id)) as id FROM product WHERE parent_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        return array_column($childrenIds, 'id');
    }

    private function getCategoryChildren(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $query = $this->connection->createQueryBuilder();

        $query->select('category.id, category.type');
        $query->from('category');

        foreach ($ids as $id) {
            $key = 'id' . $id;
            $query->orWhere('category.type != :type AND category.path LIKE :' . $key);
            $query->setParameter($key, '%' . $id . '%');
        }

        $query->setParameter('type', CategoryDefinition::TYPE_LINK);

        $children = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

        if (!$children) {
            return [];
        }

        return Uuid::fromBytesToHexList($children);
    }
}
