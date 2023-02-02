<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;

class CategoryRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
    protected string $navigationId;

    public function __construct(string $navigationId, array $tags, Request $request, StoreApiResponse $response, SalesChannelContext $context, ?Criteria $criteria)
    {
        parent::__construct($tags, $request, $response, $context, $criteria);
        $this->navigationId = $navigationId;
    }

    public function getNavigationId(): string
    {
        return $this->navigationId;
    }
}
