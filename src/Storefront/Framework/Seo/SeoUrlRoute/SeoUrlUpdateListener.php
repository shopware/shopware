<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlRoute;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SeoUrlUpdateListener implements EventSubscriberInterface
{
    /**
     * @var SeoUrlUpdater
     */
    private $seoUrlUpdater;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(SeoUrlUpdater $seoUrlUpdater, Connection $connection)
    {
        $this->seoUrlUpdater = $seoUrlUpdater;
        $this->connection = $connection;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductEvents::PRODUCT_INDEXER_EVENT => 'updateProductUrls',
        ];
    }

    public function updateProductUrls(ProductIndexerEvent $event): void
    {
        $ids = array_merge($event->getIds(), $this->getChildrenIds($event->getIds()));

        $this->seoUrlUpdater->update(ProductPageSeoUrlRoute::ROUTE_NAME, $ids);
    }

    private function getChildrenIds(array $ids): array
    {
        $childrenIds = $this->connection->fetchAll(
            'SELECT DISTINCT LOWER(HEX(id)) as id FROM product WHERE parent_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        return array_column($childrenIds, 'id');
    }
}
