<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheRouteWarmer;
use Shopware\Storefront\Framework\Cache\CacheWarmer\WarmUpMessage;

class ProductRouteWarmer implements CacheRouteWarmer
{
    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var ProductDefinition
     */
    private $definition;

    /**
     * @internal
     */
    public function __construct(IteratorFactory $iteratorFactory, ProductDefinition $definition)
    {
        $this->iteratorFactory = $iteratorFactory;
        $this->definition = $definition;
    }

    public function createMessage(SalesChannelDomainEntity $domain, ?array $offset): ?WarmUpMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->definition, $offset);
        $query = $iterator->getQuery();
        $query
            ->innerJoin('`product`', '`product_visibility`', 'pv',
                'pv.sales_channel_id = :salesChannelId
                AND pv.product_id = `product`.id
                AND pv.product_version_id = `product`.version_id
                AND pv.visibility > 0'
            )
            ->leftJoin('`product`', '`product`', 'pp', 'pp.id = `product`.parent_id')
            ->andWhere('COALESCE (`product`.active, `pp`.active)')
            ->distinct()
            ->setMaxResults(10)
            ->setParameter('salesChannelId', Uuid::fromHexToBytes($domain->getSalesChannelId()));

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        $ids = array_map(function ($id) {
            return ['productId' => $id];
        }, $ids);

        return new WarmUpMessage('frontend.detail.page', $ids, $iterator->getOffset());
    }
}
