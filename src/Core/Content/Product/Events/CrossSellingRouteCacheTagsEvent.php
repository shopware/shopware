<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class CrossSellingRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
    public function __construct(
        protected string $productId,
        array $tags,
        Request $request,
        StoreApiResponse $response,
        SalesChannelContext $context,
        ?Criteria $criteria
    ) {
        parent::__construct($tags, $request, $response, $context, $criteria);
    }

    public function getProductId(): string
    {
        return $this->productId;
    }
}
