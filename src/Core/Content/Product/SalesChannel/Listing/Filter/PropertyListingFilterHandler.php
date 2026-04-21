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
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
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

        if (!$request->request->get(self::FILTER_ENABLED_REQUEST_PARAM, true) && $groupIds === []) {
            return null;
        }

        return $this->getPropertyFilter($request, $groupIds);
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        $ids = $this->collectOptionIds($result);

        if ($ids === []) {
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

        // remove id results to prevent wrong usages. Group-aware aggregations
        // produce additional `properties-<groupId>` / `options-<groupId>` keys that
        // must also be dropped so downstream consumers only see the hydrated
        // `properties` EntityResult.
        foreach ($this->getAggregationResultNames($aggregations) as $name) {
            $aggregations->remove($name);
        }
        $aggregations->remove('configurators');

        $aggregations->add(new EntityResult('properties', $groups));
    }

    /**
     * @return list<string>
     */
    private function getAggregationResultNames(AggregationResultCollection $aggregations): array
    {
        $names = [];
        foreach ($aggregations as $aggregation) {
            $name = $aggregation->getName();
            if ($name === 'properties' || $name === 'options' || str_starts_with($name, 'properties-') || str_starts_with($name, 'options-')) {
                $names[] = $name;
            }
        }

        return $names;
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

        if ($ids === []) {
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

        $groupFilters = [];
        foreach ($grouped as $groupId => $options) {
            $groupFilters[(string) $groupId] = new OrFilter([
                new EqualsAnyFilter('product.optionIds', $options),
                new EqualsAnyFilter('product.propertyIds', $options),
            ]);
        }

        $aggregations = $this->buildGroupAwareAggregations($groupFilters);

        // `exclude = true` ensures the AggregationListingProcessor does not re-add the
        // combined property filter to the reduced aggregations. The group-aware
        // FilterAggregations we build ourselves embed only the *other* property groups'
        // filters, so sibling options of a still-selected group are no longer disabled
        // after another group's selection is removed. Non-property listing filters
        // (manufacturer, rating, ...) are still applied via the excluded filter list.
        return new Filter('properties', true, $aggregations, new AndFilter(array_values($groupFilters)), $ids, true);
    }

    /**
     * Build property/option aggregations that are aware of which property group each selected
     * option belongs to. For each selected group the aggregation excludes that group's own
     * sub-filter (but keeps every other group's filter) so the bucket counts reflect the
     * products that would match when that group's selection is lifted. Options belonging to
     * groups without any active selection are aggregated against *all* selected group filters
     * so narrowing across groups continues to work.
     *
     * @param array<string, OrFilter> $groupFilters map of property-group-id => OR-filter of that group's selected options
     *
     * @return list<FilterAggregation>
     */
    private function buildGroupAwareAggregations(array $groupFilters): array
    {
        $aggregations = [];
        $selectedGroupIds = array_keys($groupFilters);

        foreach ($groupFilters as $groupId => $_filter) {
            $otherFilters = [];
            foreach ($groupFilters as $otherGroupId => $otherFilter) {
                if ($otherGroupId === $groupId) {
                    continue;
                }
                $otherFilters[] = $otherFilter;
            }

            $propertyName = 'properties-' . $groupId;
            $optionName = 'options-' . $groupId;

            $aggregations[] = new FilterAggregation(
                $propertyName . '-filter',
                new TermsAggregation($propertyName, 'product.properties.id'),
                array_merge(
                    [new EqualsFilter('product.properties.groupId', $groupId)],
                    $otherFilters
                )
            );

            $aggregations[] = new FilterAggregation(
                $optionName . '-filter',
                new TermsAggregation($optionName, 'product.options.id'),
                array_merge(
                    [new EqualsFilter('product.options.groupId', $groupId)],
                    $otherFilters
                )
            );
        }

        // Catch-all aggregation for property groups without an active selection. Options in
        // these groups must still be narrowed by *all* currently selected group filters so
        // the cross-group narrowing behaviour is preserved.
        $allSelected = array_values($groupFilters);

        $aggregations[] = new FilterAggregation(
            'properties-filter',
            new TermsAggregation('properties', 'product.properties.id'),
            array_merge(
                [new NotFilter(NotFilter::CONNECTION_AND, [
                    new EqualsAnyFilter('product.properties.groupId', $selectedGroupIds),
                ])],
                $allSelected
            )
        );

        $aggregations[] = new FilterAggregation(
            'options-filter',
            new TermsAggregation('options', 'product.options.id'),
            array_merge(
                [new NotFilter(NotFilter::CONNECTION_AND, [
                    new EqualsAnyFilter('product.options.groupId', $selectedGroupIds),
                ])],
                $allSelected
            )
        );

        return $aggregations;
    }

    /**
     * @return array<int, non-falsy-string>
     */
    private function collectOptionIds(ProductListingResult $result): array
    {
        $aggregations = $result->getAggregations();

        $ids = [];
        foreach ($aggregations as $aggregation) {
            $name = $aggregation->getName();
            if ($name !== 'properties' && $name !== 'options' && !str_starts_with($name, 'properties-') && !str_starts_with($name, 'options-')) {
                continue;
            }

            if (!$aggregation instanceof TermsResult) {
                continue;
            }

            $ids = array_merge($ids, $aggregation->getKeys());
        }

        return array_values(array_unique(array_filter($ids)));
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

        $return = [];
        foreach ((array) $ids as $id) {
            if (!\is_string($id)) {
                continue;
            }
            if (Uuid::isValid($id)) {
                $return[] = $id;
            }
        }

        return $return;
    }
}
