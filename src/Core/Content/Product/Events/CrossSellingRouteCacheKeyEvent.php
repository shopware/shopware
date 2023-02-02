<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class CrossSellingRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
    protected string $productId;

    public function __construct(string $productId, array $parts, Request $request, SalesChannelContext $context, ?Criteria $criteria)
    {
        parent::__construct($parts, $request, $context, $criteria);
        $this->productId = $productId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }
}
