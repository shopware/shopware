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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('sales-channel')]
class SeoUrlUpdateListener implements EventSubscriberInterface
{
    final public const CATEGORY_SEO_URL_UPDATER = 'category.seo-url';
    final public const PRODUCT_SEO_URL_UPDATER = 'product.seo-url';
    final public const LANDING_PAGE_SEO_URL_UPDATER = 'landing_page.seo-url';

    /**
     * @internal
     */
    public function __construct(
        private readonly SeoUrlUpdater $seoUrlUpdater,
        private readonly Connection $connection,
        private readonly EntityIndexerRegistry $indexerRegistry
    ) {
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

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
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

        $ids = array_merge(array_values($event->getIds()), $this->getCategoryChildren($event->getIds()));

        $this->seoUrlUpdater->update(NavigationPageSeoUrlRoute::ROUTE_NAME, $ids);
    }

    public function updateProductUrls(ProductIndexerEvent $event): void
    {
        if (\in_array(self::PRODUCT_SEO_URL_UPDATER, $event->getSkip(), true)) {
            return;
        }

        $this->seoUrlUpdater->update(ProductPageSeoUrlRoute::ROUTE_NAME, array_values($event->getIds()));
    }

    public function updateLandingPageUrls(LandingPageIndexerEvent $event): void
    {
        if (\in_array(self::LANDING_PAGE_SEO_URL_UPDATER, $event->getSkip(), true)) {
            return;
        }

        $this->seoUrlUpdater->update(LandingPageSeoUrlRoute::ROUTE_NAME, array_values($event->getIds()));
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function getCategoryChildren(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $query = $this->connection->createQueryBuilder();

        $query->select('category.id');
        $query->from('category');

        foreach ($ids as $id) {
            $key = 'id' . $id;
            $query->orWhere('category.type != :type AND category.path LIKE :' . $key);
            $query->setParameter($key, '%' . $id . '%');
        }

        $query->setParameter('type', CategoryDefinition::TYPE_LINK);

        $children = $query->executeQuery()->fetchFirstColumn();

        if (!$children) {
            return [];
        }

        /** @var list<string> $ids */
        $ids = Uuid::fromBytesToHexList($children);

        return $ids;
    }
}
