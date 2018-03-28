<?php declare(strict_types=1);

namespace Shopware\Storefront\Subscriber;

use Shopware\Api\Entity\Search\Aggregation\AggregationResult;
use Shopware\Api\Entity\Search\Aggregation\EntityAggregation;
use Shopware\Api\Entity\Search\AggregatorResult;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\NestedQuery;
use Shopware\Api\Entity\Search\Query\Query;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Product\Collection\ProductManufacturerBasicCollection;
use Shopware\Api\Product\Definition\ProductManufacturerDefinition;
use Shopware\Api\Product\Repository\ProductManufacturerRepository;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Shopware\Storefront\Page\Listing\AggregationView\ListAggregation;
use Shopware\Storefront\Page\Listing\AggregationView\ListItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ManufacturerAggregationSubscriber implements EventSubscriberInterface
{
    public const PRODUCT_MANUFACTURER_ID = 'product.manufacturerJoinId';

    public const MANUFACTURER_PARAMETER = self::AGGREGATION_NAME;

    public const AGGREGATION_NAME = 'manufacturer';

    /**
     * @var ProductManufacturerRepository
     */
    private $manufacturerRepository;

    public function __construct(ProductManufacturerRepository $manufacturerRepository)
    {
        $this->manufacturerRepository = $manufacturerRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            ListingEvents::PAGE_CRITERIA_CREATED_EVENT => 'buildCriteria',
            ListingEvents::LISTING_PAGE_LOADED_EVENT => 'buildAggregationView',
        ];
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $event->getCriteria()->addAggregation(
            new EntityAggregation(
                self::PRODUCT_MANUFACTURER_ID,
                ProductManufacturerDefinition::class,
                self::MANUFACTURER_PARAMETER
            )
        );

        if (!$request->query->has(self::MANUFACTURER_PARAMETER)) {
            return;
        }

        $names = $request->query->get(self::MANUFACTURER_PARAMETER, '');
        $names = array_filter(explode('|', $names));

        $search = new Criteria();
        $search->addFilter(new TermsQuery('product_manufacturer.name', $names));
        $ids = $this->manufacturerRepository->searchIds($search, $event->getContext());

        if (empty($ids->getIds())) {
            return;
        }

        $query = new TermsQuery(self::PRODUCT_MANUFACTURER_ID, $ids->getIds());

        $event->getCriteria()->addPostFilter($query);
    }

    public function buildAggregationView(ListingPageLoadedEvent $event): void
    {
        $result = $event->getPage()->getProducts()->getAggregationResult();

        if ($result === null) {
            return;
        }

        $aggregations = $result->getAggregations();

        /* @var AggregatorResult $result */
        if (!$aggregations->has(self::AGGREGATION_NAME)) {
            return;
        }

        /** @var AggregationResult $aggregation */
        $aggregation = $aggregations->get(self::AGGREGATION_NAME);

        $criteria = $event->getPage()->getCriteria();

        $filter = $this->getFilter($criteria->getPostFilters());

        $active = $filter !== null;

        $actives = $filter ? $filter->getValue() : [];

        /** @var ProductManufacturerBasicCollection $values */
        $values = $aggregation->getResult();

        $items = [];
        foreach ($values as $manufacturer) {
            $item = new ListItem(
                $manufacturer->getName(),
                \in_array($manufacturer->getId(), $actives, true),
                $manufacturer->getName()
            );

            $item->addExtension(self::AGGREGATION_NAME, $manufacturer);
            $items[] = $item;
        }

        $event->getPage()->getAggregations()->add(
            new ListAggregation(self::AGGREGATION_NAME, $active, 'Manufacturer', self::AGGREGATION_NAME, $items)
        );
    }

    private function getFilter(NestedQuery $nested): ?TermsQuery
    {
        /** @var Query $query */
        foreach ($nested->getQueries() as $query) {
            if ($query instanceof TermsQuery && $query->getField() === self::PRODUCT_MANUFACTURER_ID) {
                return $query;
            }

            if (!$query instanceof NestedQuery || !$query instanceof NotQuery) {
                continue;
            }

            $found = $this->getFilter($query);

            if ($found) {
                return $found;
            }
        }

        return null;
    }
}
