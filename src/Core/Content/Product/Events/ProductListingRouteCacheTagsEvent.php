<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;

class ProductListingRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
    protected string $categoryId;

    public function __construct(array $tags, string $categoryId, Request $request, StoreApiResponse $response, SalesChannelContext $context, Criteria $criteria)
    {
        $this->categoryId = $categoryId;
        parent::__construct($tags, $request, $response, $context, $criteria);
    }

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }
}
