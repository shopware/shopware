<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Pagelet\Listing\ListingPageletCriteriaCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PriceAggregationSubscriber implements EventSubscriberInterface
{
    public const PRICE_FIELD = 'product.listingPrices';

    public const MIN_PRICE_PARAMETER = 'min-price';

    public const MAX_PRICE_PARAMETER = 'max-price';

    public const AGGREGATION_NAME = 'price';

    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::LISTING_PAGELET_CRITERIA_CREATED_EVENT => 'buildCriteria',
        ];
    }

    public function buildCriteria(ListingPageletCriteriaCreatedEvent $event): void
    {
        $event->getCriteria()->addAggregation(
            new StatsAggregation(self::PRICE_FIELD, self::AGGREGATION_NAME, false, false, false, true, true)
        );

        $request = $event->getRequest();

        $min = $request->query->get(self::MIN_PRICE_PARAMETER);
        $max = $request->query->get(self::MAX_PRICE_PARAMETER);

        $range = [];
        if ($min !== null) {
            $range[RangeFilter::GTE] = (float) $min;
        }
        if ($max !== null) {
            $range[RangeFilter::LTE] = (float) $max;
        }

        if (empty($range)) {
            return;
        }
        $query = new RangeFilter(self::PRICE_FIELD, $range);

        $event->getCriteria()->addPostFilter($query);
    }
}
