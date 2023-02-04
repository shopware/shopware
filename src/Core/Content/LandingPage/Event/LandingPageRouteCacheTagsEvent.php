<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;

#[Package('content')]
class LandingPageRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
    public function __construct(
        protected string $landingPageId,
        array $tags,
        Request $request,
        StoreApiResponse $response,
        SalesChannelContext $context,
        ?Criteria $criteria
    ) {
        parent::__construct($tags, $request, $response, $context, $criteria);
    }

    public function getLandingPageId(): string
    {
        return $this->landingPageId;
    }
}
