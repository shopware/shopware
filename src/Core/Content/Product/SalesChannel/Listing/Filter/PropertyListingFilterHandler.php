<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\Filter;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class PropertyListingFilterHandler extends AbstractListingFilterHandler
{
    final public const FILTER_ENABLED_REQUEST_PARAM = 'property-filter';

    final public const PROPERTY_GROUP_IDS_REQUEST_PARAM = 'property-whitelist';

    /**
     * @param EntityRepository<PropertyGroupCollection> $groupRepository
     * @param EntityRepository<PropertyGroupOptionCollection> $optionRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $groupRepository,
        private readonly EntityRepository $optionRepository,
        private readonly Connection $connection
    ) {
    }

    public function getDecorated(): AbstractListingFilterHandler
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(Request $request, SalesChannelContext $context): ?Filter
    {
        $groupIds = $request->request->all(self::PROPERTY_GROUP_IDS_REQUEST_PARAM);

        if (!$request->request->get(self::FILTER_ENABLED_REQUEST_PARAM, true) && empty($groupIds)) {
            return null;
        }

        return $this->getPropertyFilter($request, $groupIds);
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        $ids = $this->collectOptionIds($result);

        if (empty($ids)) {
            return;
        }

        $chunkIds = array_chunk($ids, 1000);

        $optionCriteria = new Criteria();
        $optionCriteria->addAssociation('media');
        $optionCriteria->setTitle('product-listing::property-filter');

        $options = [];
        $groupIds = [];

        foreach ($chunkIds as $chunk) {
            $cloned = clone $optionCriteria;
            $cloned->setIds($chunk);

            $entities = $this->optionRepository->search($cloned, $context->getContext())->getEntities();

            $options = array_merge($options, $entities->getElements());

            foreach ($entities as $option) {
                if (!isset($groupIds[$option->getGroupId()])) {
                    $groupIds[$option->getGroupId()] = true;
                }
            }
        }

        $groupCriteria = new Criteria();
        $groupCriteria->setTitle('product-listing::property-group-filter');
        $groupCriteria->addFilter(new EqualsFilter('filterable', true));

        $groups = new PropertyGroupCollection();

        $chunkIds = array_chunk(array_keys($groupIds), 1000);

        foreach ($chunkIds as $chunk) {
            $cloned = clone $groupCriteria;

            $cloned->setIds($chunk);

            $groupResult = $this->groupRepository->search($cloned, $context->getContext());

            $groups->fill($groupResult->getElements());
        }

        foreach ($groups as $group) {
            $group->setOptions(new PropertyGroupOptionCollection());
        }

        foreach ($options as $option) {
            $groups->get($option->getGroupId())?->getOptions()?->add($option);
        }

        $groups->sortByPositions();
        $groups->sortByConfig();

        $aggregations = $result->getAggregations();

        // remove id results to prevent wrong usages
        $aggregations->remove('properties');
        $aggregations->remove('configurators');
        $aggregations->remove('options');

        $aggregations->add(new EntityResult('properties', $groups));
    }

    /**
     * @param array<string>|null $groupIds
     */
    private function getPropertyFilter(Request $request, ?array $groupIds = null): Filter
    {
        $ids = $this->getPropertyIds($request);

        $propertyAggregation = new TermsAggregation('properties', 'product.properties.id');

        $optionAggregation = new TermsAggregation('options', 'product.options.id');

        if ($groupIds) {
            $propertyAggregation = new FilterAggregation(
                'properties-filter',
                $propertyAggregation,
                [new EqualsAnyFilter('product.properties.groupId', $groupIds)]
            );

            $optionAggregation = new FilterAggregation(
                'options-filter',
                $optionAggregation,
                [new EqualsAnyFilter('product.options.groupId', $groupIds)]
            );
        }

        $aggregations = [$propertyAggregation, $optionAggregation];

        if (empty($ids)) {
            return new Filter('properties', false, $aggregations, new AndFilter([]), [], false);
        }

        $grouped = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(property_group_id)) as property_group_id, LOWER(HEX(id)) as id
             FROM property_group_option
             WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $grouped = FetchModeHelper::group($grouped, static fn ($row): string => (string) $row['id']);

        $filters = [];
        foreach ($grouped as $options) {
            $filters[] = new OrFilter([
                new EqualsAnyFilter('product.optionIds', $options),
                new EqualsAnyFilter('product.propertyIds', $options),
            ]);
        }

        return new Filter('properties', true, $aggregations, new AndFilter($filters), $ids, false);
    }

    /**
     * @return array<int, non-falsy-string>
     */
    private function collectOptionIds(ProductListingResult $result): array
    {
        $aggregations = $result->getAggregations();

        $properties = $aggregations->get('properties');
        \assert($properties instanceof TermsResult || $properties === null);

        $options = $aggregations->get('options');
        \assert($options instanceof TermsResult || $options === null);

        $options = $options ? $options->getKeys() : [];
        $properties = $properties ? $properties->getKeys() : [];

        return array_unique(array_filter([...$options, ...$properties]));
    }

    /**
     * @return list<string>
     */
    private function getPropertyIds(Request $request): array
    {
        $ids = $request->query->get('properties', '');
        if ($request->isMethod(Request::METHOD_POST)) {
            $ids = $request->request->get('properties', '');
        }

        if (\is_string($ids)) {
            $ids = explode('|', $ids);
        }

        /** @var list<string> $ids */
        $ids = array_filter((array) $ids, function ($id) {
            return Uuid::isValid((string) $id);
        });

        return $ids;
    }
}
