<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ProductListingRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
    protected string $categoryId;

    public function __construct(array $parts, string $categoryId, Request $request, SalesChannelContext $context, Criteria $criteria)
    {
        parent::__construct($parts, $request, $context, $criteria);
        $this->categoryId = $categoryId;
    }

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }
}
