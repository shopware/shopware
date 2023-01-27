<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('content')]
class CategoryRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
    /**
     * @param array<mixed> $parts
     */
    public function __construct(
        protected string $navigationId,
        array $parts,
        Request $request,
        SalesChannelContext $context,
        ?Criteria $criteria
    ) {
        parent::__construct($parts, $request, $context, $criteria);
    }

    public function getNavigationId(): string
    {
        return $this->navigationId;
    }
}
