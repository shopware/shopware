<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class ProductPageCriteriaEvent extends Event implements ShopwareSalesChannelEvent
{
    protected string $productId;

    protected Criteria $criteria;

    protected SalesChannelContext $context;

    public function __construct(string $productId, Criteria $criteria, SalesChannelContext $context)
    {
        $this->productId = $productId;
        $this->criteria = $criteria;
        $this->context = $context;
    }

    public function getProductId(): string
    {
        return $this->productId;
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
