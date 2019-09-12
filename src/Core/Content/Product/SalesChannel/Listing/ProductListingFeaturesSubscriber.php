<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductListingFeaturesSubscriber implements EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductListingCriteriaEvent::class => 'handleRequest',
            ProductSuggestCriteriaEvent::class => 'handleSuggestRequest',
            ProductSearchCriteriaEvent::class => 'handleRequest',
        ];
    }

    public function handleSuggestRequest(ProductListingCriteriaEvent $event): void
    {
        $criteria = $event->getCriteria();

        $criteria->addAssociation('cover.media');

        $criteria->addGroupField(new FieldGrouping('displayGroup'));
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsFilter('displayGroup', null)]
            )
        );
    }

    public function handleRequest(ProductListingCriteriaEvent $event): void
    {
        $request = $event->getRequest();

        $criteria = $event->getCriteria();

        $criteria->addAssociation('cover.media');

        $criteria->addGroupField(new FieldGrouping('displayGroup'));
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsFilter('displayGroup', null)]
            )
        );

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
        $limit = $request->query->getInt('limit', 24);
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
            new EntityAggregation('manufacturer', 'product.manufacturerId', 'product_manufacturer')
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
            new EntityAggregation('properties', 'product.properties.id', 'property_group_option')
        );
        $criteria->addAggregation(
            new EntityAggregation('options', 'product.options.id', 'property_group_option')
        );

        $ids = $request->query->get('properties', '');
        $ids = explode('|', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return;
        }

        $grouped = $this->connection->fetchAll(
            'SELECT LOWER(HEX(property_group_id)), LOWER(HEX(id)) as id FROM property_group_option WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $grouped = FetchModeHelper::group($grouped);

        foreach ($grouped as $options) {
            $options = array_column($options, 'id');

            $criteria->addPostFilter(
                new MultiFilter(
                    MultiFilter::CONNECTION_OR,
                    [
                        new EqualsAnyFilter('product.optionIds', $options),
                        new EqualsAnyFilter('product.propertyIds', $options),
                    ]
                )
            );
        }
    }

    private function handlePriceFilter(Request $request, Criteria $criteria): void
    {
        $criteria->addAggregation(
            new StatsAggregation('price', 'product.price')
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
