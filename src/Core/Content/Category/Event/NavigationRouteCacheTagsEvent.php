<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;

#[Package('content')]
class NavigationRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
    public function __construct(
        array $tags,
        protected string $active,
        protected string $rootId,
        protected int $depth,
        Request $request,
        StoreApiResponse $response,
        SalesChannelContext $context,
        Criteria $criteria
    ) {
        parent::__construct($tags, $request, $response, $context, $criteria);
    }

    public function getActive(): string
    {
        return $this->active;
    }

    public function getRootId(): string
    {
        return $this->rootId;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }
}
