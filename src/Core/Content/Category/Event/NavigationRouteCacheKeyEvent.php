<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NavigationRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
    protected string $active;

    protected string $rootId;

    protected int $depth;

    public function __construct(array $parts, string $active, string $rootId, int $depth, Request $request, SalesChannelContext $context, Criteria $criteria)
    {
        parent::__construct($parts, $request, $context, $criteria);
        $this->active = $active;
        $this->rootId = $rootId;
        $this->depth = $depth;
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
