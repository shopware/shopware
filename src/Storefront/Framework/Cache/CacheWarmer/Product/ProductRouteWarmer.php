<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheRouteWarmer;
use Shopware\Storefront\Framework\Cache\CacheWarmer\WarmUpMessage;

#[Package('storefront')]
class ProductRouteWarmer implements CacheRouteWarmer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly ProductDefinition $definition
    ) {
    }

    public function createMessage(SalesChannelDomainEntity $domain, ?array $offset): ?WarmUpMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->definition, $offset);
        $query = $iterator->getQuery();
        $query
            ->leftJoin('`product`', '`product`', 'pp', 'pp.id = `product`.parent_id')
            ->andWhere('COALESCE (`product`.active, `pp`.active)')
            ->distinct()
            ->setMaxResults(10);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        $ids = array_map(fn ($id) => ['productId' => $id], $ids);

        return new WarmUpMessage('frontend.detail.page', $ids, $iterator->getOffset());
    }
}
