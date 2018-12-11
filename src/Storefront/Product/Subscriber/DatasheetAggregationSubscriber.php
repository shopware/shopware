<?php declare(strict_types=1);

namespace Shopware\Storefront\Product\Subscriber;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\Core\Content\Configuration\ConfigurationGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Storefront\Listing\Event\ListingEvents;
use Shopware\Storefront\Listing\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Listing\Event\ListingPageRequestEvent;
use Shopware\Storefront\Listing\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Listing\Page\AggregationView\ListAggregation;
use Shopware\Storefront\Listing\Page\AggregationView\ListItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DatasheetAggregationSubscriber implements EventSubscriberInterface
{
    public const DATASHEET_FILTER_FIELD = 'product.datasheetIds';

    public const DATASHEET_PARAMETER = 'option';

    public const AGGREGATION_NAME = 'datasheet';

    public const DATASHEET_AGGREGATION_FIELD = 'product.datasheet.id';

    public static function getSubscribedEvents()
    {
        return [
            ListingEvents::CRITERIA_CREATED => 'buildCriteria',
            ListingEvents::LOADED => 'buildPage',
            ListingEvents::REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ListingPageRequestEvent $event): void
    {
        $listingRequest = $event->getListingPageRequest();

        $request = $event->getRequest();

        if (!$request->query->has(self::DATASHEET_PARAMETER)) {
            return;
        }

        $ids = $request->query->get(self::DATASHEET_PARAMETER, '');
        $ids = array_filter(explode('|', $ids));

        if (empty($ids)) {
            return;
        }

        $listingRequest->setDatasheetIds($ids);
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $event->getCriteria()->addAggregation(
            new EntityAggregation(
                self::DATASHEET_AGGREGATION_FIELD,
                ConfigurationGroupOptionDefinition::class,
                self::AGGREGATION_NAME
            )
        );

        if (empty($request->getDatasheetIds())) {
            return;
        }

        $ids = $request->getDatasheetIds();

        $query = new EqualsAnyFilter(self::DATASHEET_FILTER_FIELD, $ids);

        //add query as extension to transport active aggregation view elements
        $criteria = $event->getCriteria();
        $criteria->addExtension(self::AGGREGATION_NAME, new ArrayEntity(['ids' => $ids]));
        $criteria->addPostFilter($query);
    }

    public function buildPage(ListingPageLoadedEvent $event): void
    {
        $page = $event->getPage();

        $result = $page->getProducts()->getAggregations();

        if ($result->count() <= 0) {
            return;
        }

        if (!$result->has(self::AGGREGATION_NAME)) {
            return;
        }

        /** @var EntityAggregationResult $aggregation */
        $aggregation = $result->get(self::AGGREGATION_NAME);

        /** @var ArrayEntity|null $filter */
        $filter = $page->getCriteria()->getExtension(self::AGGREGATION_NAME);

        $active = $filter !== null;

        $actives = $filter ? $filter->get('ids') : [];

        /** @var ConfigurationGroupOptionCollection $values */
        $values = $aggregation->getEntities();

        if ($values->count() === 0) {
            return;
        }

        $groups = $values->groupByConfigurationGroups();

        /** @var ConfigurationGroupEntity $group */
        foreach ($groups as $group) {
            $items = [];

            foreach ($group->getOptions() as $option) {
                $item = new ListItem(
                    $option->getName(),
                    \in_array($option->getId(), $actives, true),
                    $option->getId()
                );

                $item->addExtension('option', $option);
                $items[] = $item;
            }

            $page->getAggregations()->add(
                new ListAggregation('option', $active, $group->getName(), 'option', $items)
            );
        }
    }
}
