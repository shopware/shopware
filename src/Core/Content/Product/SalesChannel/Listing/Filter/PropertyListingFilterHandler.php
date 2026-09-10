<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing\Filter;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
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

            $groups->fill($groupResult->getEntities()->getElements());
        }

        foreach ($groups as $group) {
            $group->setOptions(new PropertyGroupOptionCollection());
        }

        foreach ($options as $option) {
            $groups->get($option->getGroupId())?->getOptions()?->add($option);
        }

        $groups->sortByPositions();
        $groups->sortByConfig($context->getLanguageInfo()->localeCode);

        $aggregations = $result->getAggregations();

        $this->removePropertyAggregationResults($aggregations);
        $aggregations->remove('configurators');

        $aggregations->add(new EntityResult('properties', $groups));
    }

    /**
     * @param array<string>|null $groupIds property-group whitelist (from `property-whitelist` request param)
     */
    private function getPropertyFilter(Request $request, ?array $groupIds = null): Filter
    {
        $ids = $this->getPropertyIds($request);

        /* No selection: unconstrained property listing, no post-filter, no group-aware buckets. */
        if ($ids === []) {
            return new Filter(
                'properties',
                false,
                $this->buildLegacyAggregations($groupIds),
                new AndFilter([]),
                [],
                false
            );
        }

        $groupOrFilters = $this->buildGroupOrFilters($ids);
        $postFilter = new AndFilter(array_values($groupOrFilters));

        /*
         * Group-aware aggregations only matter when the storefront's filter refresh asks for
         * reduced aggregations ("Disable filter options without results"). On any other request
         * — initial page load, deep-linked URL with `properties=...`, search API consumer — we
         * keep the legacy unconstrained aggregations so the full property tree renders.
         */
        if (RequestParamHelper::get($request, 'reduce-aggregations') === null) {
            return new Filter(
                'properties',
                true,
                $this->buildLegacyAggregations($groupIds),
                $postFilter,
                $ids,
                false
            );
        }

        /*
         * reduce-aggregations mode: emit group-aware buckets and flip `exclude` so the
         * AggregationListingProcessor does not re-apply the combined property filter on top.
         * Cross-group constraints are managed inside each FilterAggregation below; non-property
         * listing filters (manufacturer, rating, ...) are still added by the processor via the
         * excluded-filter list.
         */
        return new Filter(
            'properties',
            true,
            $this->buildGroupAwareAggregations($groupOrFilters, $groupIds),
            $postFilter,
            $ids,
            true
        );
    }

    /**
     * Legacy property/option aggregations: plain TermsAggregation, optionally wrapped in a
     * FilterAggregation that scopes them to the property-group whitelist. Mirrors the
     * pre-#15812 shape so consumers outside the filter-refresh AJAX see the unchanged response.
     *
     * @param array<string>|null $groupIds property-group whitelist
     *
     * @return list<Aggregation>
     */
    private function buildLegacyAggregations(?array $groupIds): array
    {
        $properties = new TermsAggregation('properties', 'product.properties.id');
        $options = new TermsAggregation('options', 'product.options.id');

        if (!$groupIds) {
            return [$properties, $options];
        }

        return [
            new FilterAggregation(
                'properties-filter',
                $properties,
                [new EqualsAnyFilter('product.properties.groupId', $groupIds)]
            ),
            new FilterAggregation(
                'options-filter',
                $options,
                [new EqualsAnyFilter('product.options.groupId', $groupIds)]
            ),
        ];
    }

    /**
     * Group the selected property/option ids by their property group and build one
     * OR-within-group filter per group. The map is keyed by property-group-id so callers can
     * cheaply look up "the filter for group X" when building group-aware aggregations.
     *
     * @param list<string> $ids
     *
     * @return array<string, OrFilter>
     */
    private function buildGroupOrFilters(array $ids): array
    {
        $grouped = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(property_group_id)) as property_group_id, LOWER(HEX(id)) as id
             FROM property_group_option
             WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $grouped = FetchModeHelper::group($grouped, static fn ($row): string => (string) $row['id']);

        $groupOrFilters = [];
        foreach ($grouped as $propertyGroupId => $optionIds) {
            $groupOrFilters[(string) $propertyGroupId] = new OrFilter([
                new EqualsAnyFilter('product.optionIds', $optionIds),
                new EqualsAnyFilter('product.propertyIds', $optionIds),
            ]);
        }

        return $groupOrFilters;
    }

    /**
     * Build the group-aware aggregations used in `reduce-aggregations` mode.
     *
     * Two flavours are emitted:
     *
     * - **`properties-base` / `options-base`** — all selected property-group filters applied
     *   (cross-group AND, OR-within-group). This is the only aggregation that enumerates
     *   options in groups WITHOUT a current selection, and ensures the legacy `properties` /
     *   `options` result names stay populated for backward compatibility.
     * - **`properties-group-<id>` / `options-group-<id>`** — one pair per selected group, scoped
     *   by `properties.groupId` to that group, carrying every *other* selected group's filter
     *   but lifting its own. This preserves OR-within-group semantics: sibling options stay
     *   selectable because adding them would extend the OR within their group and yield results.
     *
     * The base aggregation overlaps with per-group aggregations for selected groups; the
     * overlap is deduplicated in `collectOptionIds()`. The advantage over a `NotFilter` catch-all
     * is that this pattern works identically in DBAL and Elasticsearch — negated nested filters
     * have different semantics across the two backends.
     *
     * @param array<string, OrFilter> $groupOrFilters keyed by property-group-id
     * @param array<string>|null $groupIdWhitelist optional whitelist applied to every aggregation
     *
     * @return list<Aggregation>
     */
    private function buildGroupAwareAggregations(array $groupOrFilters, ?array $groupIdWhitelist = null): array
    {
        $allGroupFilters = array_values($groupOrFilters);

        $propertyWhitelist = $groupIdWhitelist
            ? [new EqualsAnyFilter('product.properties.groupId', $groupIdWhitelist)]
            : [];
        $optionWhitelist = $groupIdWhitelist
            ? [new EqualsAnyFilter('product.options.groupId', $groupIdWhitelist)]
            : [];

        $aggregations = [
            new FilterAggregation(
                'properties-base',
                new TermsAggregation('properties', 'product.properties.id'),
                [...$propertyWhitelist, ...$allGroupFilters]
            ),
            new FilterAggregation(
                'options-base',
                new TermsAggregation('options', 'product.options.id'),
                [...$optionWhitelist, ...$allGroupFilters]
            ),
        ];

        foreach ($groupOrFilters as $propertyGroupId => $_) {
            $otherGroupFilters = $groupOrFilters;
            unset($otherGroupFilters[$propertyGroupId]);
            $otherGroupFilters = array_values($otherGroupFilters);

            $aggregations[] = new FilterAggregation(
                'properties-group-' . $propertyGroupId,
                new TermsAggregation('properties.' . $propertyGroupId, 'product.properties.id'),
                [
                    ...$propertyWhitelist,
                    new EqualsFilter('product.properties.groupId', $propertyGroupId),
                    ...$otherGroupFilters,
                ]
            );

            $aggregations[] = new FilterAggregation(
                'options-group-' . $propertyGroupId,
                new TermsAggregation('options.' . $propertyGroupId, 'product.options.id'),
                [
                    ...$optionWhitelist,
                    new EqualsFilter('product.options.groupId', $propertyGroupId),
                    ...$otherGroupFilters,
                ]
            );
        }

        return $aggregations;
    }

    /**
     * @return array<int, non-falsy-string>
     */
    private function collectOptionIds(ProductListingResult $result): array
    {
        $ids = [];

        foreach ($result->getAggregations() as $aggregation) {
            if (!$aggregation instanceof TermsResult || !self::isPropertyAggregationName($aggregation->getName())) {
                continue;
            }

            $ids = [...$ids, ...$aggregation->getKeys()];
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Drop every property/option aggregation result we produced so consumers only see the
     * hydrated `properties` EntityResult.
     */
    private function removePropertyAggregationResults(AggregationResultCollection $aggregations): void
    {
        $names = [];
        foreach ($aggregations as $aggregation) {
            if (self::isPropertyAggregationName($aggregation->getName())) {
                $names[] = $aggregation->getName();
            }
        }

        foreach ($names as $name) {
            $aggregations->remove($name);
        }
    }

    /**
     * Aggregation-name predicate shared by `collectOptionIds()` (which harvests ids) and
     * `removePropertyAggregationResults()` (which cleans up). Matches the legacy `properties` /
     * `options` plus the group-aware `properties.<groupId>` / `options.<groupId>` produced by
     * `buildGroupAwareAggregations()`.
     */
    private static function isPropertyAggregationName(string $name): bool
    {
        return $name === 'properties'
            || $name === 'options'
            || str_starts_with($name, 'properties.')
            || str_starts_with($name, 'options.');
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
