<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ListingGateway implements ListingGatewayInterface
{
    /**
     * @var SalesChannelRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        SalesChannelRepository $productRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function search(Request $request, SalesChannelContext $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', null));

        $this->handleCategoryFilter($request, $criteria);

        $this->handlePagination($request, $criteria);

        $this->handleManufacturerFilter($request, $criteria);

        $this->handlePropertyFilter($request, $criteria);

        $this->handlePriceFilter($request, $criteria);

        $this->eventDispatcher->dispatch(
            ProductEvents::PRODUCT_LISTING_CRITERIA,
            new ProductListingCriteriaEvent($request, $criteria, $context)
        );

        $result = $this->productRepository->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProductEvents::PRODUCT_LISTING_RESULT,
            new ProductListingResultEvent($request, $result, $context)
        );

        return $result;
    }

    private function handlePagination(Request $request, Criteria $criteria): void
    {
        $limit = $request->query->get('limit', 25);
        $page = $request->query->get('p', 1);

        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit((int) $limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);
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

    private function handleCategoryFilter(Request $request, Criteria $criteria): void
    {
        $params = $request->attributes->get('_route_params');
        if (!$params) {
            throw new MissingRequestParameterException('navigationId');
        }

        if (!isset($params['navigationId'])) {
            throw new MissingRequestParameterException('navigationId');
        }

        $navigationId = $params['navigationId'];
        if (!Uuid::isValid($navigationId)) {
            throw new InvalidUuidException($navigationId);
        }

        $criteria->addFilter(new EqualsFilter('product.categoriesRo.id', $navigationId));
    }
}
