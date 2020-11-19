<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\QuickView;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.4.0 - Will implement Shopware\Core\Framework\Event\ShopwareSalesChannelEvent
 */
class MinimalQuickViewPageCriteriaEvent extends Event /*implements ShopwareSalesChannelEvent*/
{
    /**
     * @var string
     */
    protected $productId;

    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var SalesChannelContext
     */
    protected $context;

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

    /**
     * @deprecated tag:v6.4.0 - Will return Shopware\Core\Framework\Context instead
     */
    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
