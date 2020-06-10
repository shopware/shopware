<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\CrossSelling;

use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

abstract class CrossSellingProductCriteriaEvent extends Event implements ShopwareEvent
{
    /**
     * @var ProductCrossSellingEntity
     */
    private $crossSelling;

    /**
     * @var Criteria
     */
    private $criteria;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function __construct(
        ProductCrossSellingEntity $crossSelling,
        Criteria $criteria,
        SalesChannelContext $salesChannelContext
    ) {
        $this->crossSelling = $crossSelling;
        $this->criteria = $criteria;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getCrossSelling(): ProductCrossSellingEntity
    {
        return $this->crossSelling;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
