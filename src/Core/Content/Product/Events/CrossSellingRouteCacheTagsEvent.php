<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;

class CrossSellingRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
    protected string $productId;

    public function __construct(string $productId, array $tags, Request $request, StoreApiResponse $response, SalesChannelContext $context, ?Criteria $criteria)
    {
        parent::__construct($tags, $request, $response, $context, $criteria);
        $this->productId = $productId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }
}
