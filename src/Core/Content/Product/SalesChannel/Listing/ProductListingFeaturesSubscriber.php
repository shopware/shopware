<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.6.0 - reason:remove-subscriber - Will be removed, call CompositeListingProcessor directly
 *
 * @internal
 */
#[Package('inventory')]
class ProductListingFeaturesSubscriber implements EventSubscriberInterface
{
    final public const DEFAULT_SEARCH_SORT = 'score';

    /**
     * @deprecated tag:v6.6.0 - Will be removed
     *
     * @internal
     */
    final public const HANDLED_STATE = 'processor-called';

    final public const PROPERTY_GROUP_IDS_REQUEST_PARAM = 'property-whitelist';

    public function __construct(private readonly CompositeListingProcessor $processor)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingCriteriaEvent::class => 'prepare',
            ProductSearchCriteriaEvent::class => 'prepare',
            ProductSuggestCriteriaEvent::class => 'prepare',
            ProductListingResultEvent::class => 'process',
            ProductSearchResultEvent::class => 'process',
        ];
    }

    public function prepare(ProductListingCriteriaEvent $event): void
    {
        if (Feature::isActive('v6.6.0.0') || $event->getContext()->hasState(self::HANDLED_STATE)) {
            return;
        }

        $this->processor->prepare(
            $event->getRequest(),
            $event->getCriteria(),
            $event->getSalesChannelContext()
        );
    }

    public function process(ProductListingResultEvent $event): void
    {
        if (Feature::isActive('v6.6.0.0') || $event->getContext()->hasState(self::HANDLED_STATE)) {
            return;
        }

        $this->processor->process(
            $event->getRequest(),
            $event->getResult(),
            $event->getSalesChannelContext()
        );
    }
}
