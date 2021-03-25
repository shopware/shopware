<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class LandingPageRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
    protected string $landingPageId;

    public function __construct(string $landingPageId, array $parts, Request $request, SalesChannelContext $context, ?Criteria $criteria)
    {
        parent::__construct($parts, $request, $context, $criteria);
        $this->landingPageId = $landingPageId;
    }

    public function getLandingPageId(): string
    {
        return $this->landingPageId;
    }
}
