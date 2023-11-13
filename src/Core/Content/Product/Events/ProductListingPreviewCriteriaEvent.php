<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class ProductListingPreviewCriteriaEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var SalesChannelContext
     */
    protected $context;

    public function __construct(
        Criteria $criteria,
        SalesChannelContext $context
    ) {
        $this->criteria = $criteria;
        $this->context = $context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
