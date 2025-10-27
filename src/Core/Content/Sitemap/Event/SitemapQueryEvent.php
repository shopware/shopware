<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Event;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('discovery')]
final class SitemapQueryEvent extends Event implements GenericEvent, ShopwareSalesChannelEvent
{
    public function __construct(
        public readonly QueryBuilder $query,
        public readonly int $limit,
        public readonly ?int $offset,
        private readonly SalesChannelContext $salesChannelContext,
        private readonly string $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
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
