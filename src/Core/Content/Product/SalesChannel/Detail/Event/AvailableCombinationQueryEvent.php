<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail\Event;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class AvailableCombinationQueryEvent extends Event implements ShopwareSalesChannelEvent
{
    public function __construct(
        private readonly string $productId,
        private readonly QueryBuilder $queryBuilder,
        private readonly SalesChannelContext $salesChannelContext,
    ) {
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }
}
