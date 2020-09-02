<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @deprecated tag:v6.4.0 - Use `ProductPageCriteriaEvent` or `MinimalQuickViewPageCriteriaEvent` event instead
 */
class ProductLoaderCriteriaEvent extends NestedEvent
{
    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

    public function __construct(Criteria $criteria, SalesChannelContext $salesChannelContext)
    {
        $this->salesChannelContext = $salesChannelContext;
        $this->criteria = $criteria;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
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
