<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing\Subscriber;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Pagelet\Listing\ListingPageletCriteriaCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PropertiesAggregationSubscriber implements EventSubscriberInterface
{
    public const PROPERTIES_FILTER_FIELD = 'product.propertyIds';

    public const PROPERTIES_PARAMETER = 'option';

    public const AGGREGATION_NAME = 'properties';

    public const PROPERTIES_AGGREGATION_FIELD = 'product.properties.id';

    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::LISTING_PAGELET_CRITERIA_CREATED_EVENT => 'buildCriteria',
        ];
    }

    public function buildCriteria(ListingPageletCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $event->getCriteria()->addAggregation(
            new EntityAggregation(
                self::PROPERTIES_AGGREGATION_FIELD,
                PropertyGroupOptionDefinition::class,
                self::AGGREGATION_NAME
            )
        );

        $ids = $request->query->get(self::PROPERTIES_PARAMETER, '');
        $ids = array_filter(explode('|', $ids));

        if (empty($ids)) {
            return;
        }

        $query = new EqualsAnyFilter(self::PROPERTIES_FILTER_FIELD, $ids);

        //add query as extension to transport active aggregation view elements
        $criteria = $event->getCriteria();
        $criteria->addExtension(self::AGGREGATION_NAME, new ArrayEntity(['ids' => $ids]));
        $criteria->addPostFilter($query);
    }
}
