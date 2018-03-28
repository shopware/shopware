<?php

namespace Shopware\Storefront\Subscriber;

use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionBasicCollection;
use Shopware\Api\Configuration\Definition\ConfigurationGroupOptionDefinition;
use Shopware\Api\Configuration\Struct\ConfigurationGroupDetailStruct;
use Shopware\Api\Entity\Search\Aggregation\AggregationResult;
use Shopware\Api\Entity\Search\Aggregation\EntityAggregation;
use Shopware\Api\Entity\Search\AggregatorResult;
use Shopware\Api\Entity\Search\Query\NestedQuery;
use Shopware\Api\Entity\Search\Query\Query;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Page\Listing\AggregationView\ListAggregation;
use Shopware\Storefront\Page\Listing\AggregationView\ListItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DatasheetAggregationSubscriber implements EventSubscriberInterface
{
    public const DATASHEET_ID_FIELD = 'product.datasheet.id';

    public const DATASHEET_PARAMETER = 'option';

    const AGGREGATION_NAME = 'datasheet';

    public static function getSubscribedEvents()
    {
        return [
            ListingEvents::PAGE_CRITERIA_CREATED_EVENT => 'buildCriteria',
            ListingEvents::LISTING_PAGE_LOADED_EVENT => 'buildAggregationView'
        ];
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $event->getCriteria()->addAggregation(
            new EntityAggregation(
                self::DATASHEET_ID_FIELD,
                ConfigurationGroupOptionDefinition::class,
                self::AGGREGATION_NAME
            )
        );

        if (!$request->query->has(self::DATASHEET_PARAMETER)) {
            return;
        }

        $ids = $request->query->get(self::DATASHEET_PARAMETER, '');
        $ids = array_filter(explode('|', $ids));

        if (empty($ids)) {
            return;
        }

        $query = new TermsQuery(self::DATASHEET_ID_FIELD, $ids);

        //add query as extension to transport active aggregation view elements
        $event->getCriteria()->addExtension(self::AGGREGATION_NAME, $query);
        $event->getCriteria()->addPostFilter($query);
    }

    public function buildAggregationView(ListingPageLoadedEvent $event): void
    {
        $page = $event->getPage();

        $result = $page->getProducts()->getAggregationResult();

        if ($result === null) {
            return;
        }

        $aggregations = $result->getAggregations();

        /** @var AggregatorResult $result */
        if (!$aggregations->has(self::AGGREGATION_NAME)) {
            return;
        }

        /** @var AggregationResult $aggregation */
        $aggregation = $aggregations->get(self::AGGREGATION_NAME);

        /** @var TermsQuery|null $filter */
        $filter = $page->getCriteria()->getExtension(self::AGGREGATION_NAME);

        $active = $filter !== null;

        $actives = $filter ? $filter->getValue() : [];

        /** @var ConfigurationGroupOptionBasicCollection $values */
        $values = $aggregation->getResult();

        if (!$values || $values->count() <= 0) {
            return;
        }

        $groups = $values->groupByConfigurationGroups();

        /** @var ConfigurationGroupDetailStruct $group */
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