<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('sales-channel')]
class SitemapSalesChannelCriteriaEvent extends Event implements ShopwareEvent
{
    public function __construct(
        private readonly Criteria $criteria,
        private readonly Context $context
    ) {
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
