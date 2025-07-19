<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 */
class CategoryRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
    /**
     * @param array<string> $tags
     */
    public function __construct(
        protected string $navigationId,
        array $tags,
        Request $request,
        StoreApiResponse $response,
        SalesChannelContext $context,
        ?Criteria $criteria
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        parent::__construct($tags, $request, $response, $context, $criteria);
    }

    public function getNavigationId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->navigationId;
    }
}
