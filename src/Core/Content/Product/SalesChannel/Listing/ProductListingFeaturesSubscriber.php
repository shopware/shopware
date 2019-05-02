<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductListingFeaturesSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductEvents::PRODUCT_LISTING_CRITERIA => 'handleRequest',
            ProductEvents::PRODUCT_SEARCH_CRITERIA => 'handleRequest',
        ];
    }

    public function handleRequest(ProductListingCriteriaEvent $event): void
    {
        $request = $event->getRequest();

        $criteria = $event->getCriteria();

        $this->handlePagination($request, $criteria);

        $this->handleManufacturerFilter($request, $criteria);

        $this->handlePropertyFilter($request, $criteria);

        $this->handlePriceFilter($request, $criteria);

        if ($request->get('no-aggregations')) {
            $criteria->resetAggregations();
        }
    }

    private function handlePagination(Request $request, Criteria $criteria): void
    {
        $limit = $request->query->getInt('limit', 25);
        $page = $request->query->getInt('p', 1);

        if ($request->isMethod(Request::METHOD_POST)) {
            $limit = $request->request->getInt('limit', $limit);
            $page = $request->request->getInt('p', $page);
        }

        $limit = $limit > 0 ? $limit : 25;
        $page = $page > 0 ? $page : 1;

        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
    }

    private function handleManufacturerFilter(Request $request, Criteria $criteria): void
    {
        $criteria->addAggregation(
            new EntityAggregation('product.manufacturerId', ProductManufacturerDefinition::class, 'manufacturer')
        );

        $ids = $request->query->get('manufacturer', '');
        $ids = explode('|', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return;
        }

        $criteria->addPostFilter(new EqualsAnyFilter('product.manufacturerId', $ids));
    }

    private function handlePropertyFilter(Request $request, Criteria $criteria): void
    {
        $criteria->addAggregation(
            new EntityAggregation('product.properties.id', PropertyGroupOptionDefinition::class, 'properties')
        );
        $criteria->addAggregation(
            new EntityAggregation('product.options.id', PropertyGroupOptionDefinition::class, 'options')
        );

        $ids = $request->query->get('properties', '');
        $ids = explode('|', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return;
        }

        $criteria->addPostFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    new EqualsAnyFilter('product.optionIds', $ids),
                    new EqualsAnyFilter('product.propertyIds', $ids),
                ]
            )
        );
    }

    private function handlePriceFilter(Request $request, Criteria $criteria): void
    {
        $criteria->addAggregation(
            new StatsAggregation('product.price', 'price', false, false, false, true, true)
        );

        $min = $request->query->get('min-price');
        $max = $request->query->get('max-price');

        if (!$min && !$max) {
            return;
        }

        $range = [];
        if ($min !== null) {
            $range[RangeFilter::GTE] = $min;
        }
        if ($max !== null) {
            $range[RangeFilter::LTE] = $max;
        }

        $criteria->addPostFilter(new RangeFilter('product.price', $range));
    }
}
