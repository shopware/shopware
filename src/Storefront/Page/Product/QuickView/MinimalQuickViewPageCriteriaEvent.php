<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\QuickView;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('storefront')]
class MinimalQuickViewPageCriteriaEvent extends Event implements ShopwareSalesChannelEvent
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $productId;

    /**
     * @var Criteria
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $criteria;

    /**
     * @var SalesChannelContext
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $context;

    public function __construct(
        string $productId,
        Criteria $criteria,
        SalesChannelContext $context
    ) {
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
