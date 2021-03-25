<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;

class LandingPageRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
    protected string $landingPageId;

    public function __construct(string $landingPageId, array $tags, Request $request, StoreApiResponse $response, SalesChannelContext $context, ?Criteria $criteria)
    {
        parent::__construct($tags, $request, $response, $context, $criteria);
        $this->landingPageId = $landingPageId;
    }

    public function getLandingPageId(): string
    {
        return $this->landingPageId;
    }
}
