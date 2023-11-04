<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\AssociationNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidLimitQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidPageQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSortQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\QueryLimitExceededException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('core')]
class RequestCriteriaBuilder
{
    private const TOTAL_COUNT_MODE_MAPPING = [
        'none' => Criteria::TOTAL_COUNT_MODE_NONE,
        'exact' => Criteria::TOTAL_COUNT_MODE_EXACT,
        'next-pages' => Criteria::TOTAL_COUNT_MODE_NEXT_PAGES,
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly AggregationParser $aggregationParser,
        private readonly ApiCriteriaValidator $validator,
        private readonly CriteriaArrayConverter $converter,
        private readonly ?int $maxLimit = null
    ) {
    }

    public function handleRequest(Request $request, Criteria $criteria, EntityDefinition $definition, Context $context): Criteria
    {
        if ($request->getMethod() === Request::METHOD_GET) {
            $criteria = $this->fromArray($request->query->all(), $criteria, $definition, $context);
        } else {
            $criteria = $this->fromArray($request->request->all(), $criteria, $definition, $context);
        }

        return $criteria;
    }

    public function toArray(Criteria $criteria): array
    {
        return $this->converter->convert($criteria);
    }

    public function fromArray(array $payload, Criteria $criteria, EntityDefinition $definition, Context $context): Criteria
    {
        return $this->parse($payload, $criteria, $definition, $context, $this->maxLimit);
    }

    public function addTotalCountMode(string $totalCountMode, Criteria $criteria): void
    {
        if (is_numeric($totalCountMode)) {
            $criteria->setTotalCountMode((int) $totalCountMode);

            // total count is out of bounds
            if ($criteria->getTotalCountMode() > 2 || $criteria->getTotalCountMode() < 0) {
                $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);
            }
        } else {
            $criteria->setTotalCountMode(self::TOTAL_COUNT_MODE_MAPPING[$totalCountMode] ?? Criteria::TOTAL_COUNT_MODE_NONE);
        }
    }

    private function parse(array $payload, Criteria $criteria, EntityDefinition $definition, Context $context, ?int $maxLimit): Criteria
    {
        $searchException = new SearchRequestException();

        if (isset($payload['ids'])) {
            if (\is_string($payload['ids'])) {
                $ids = array_filter(explode('|', $payload['ids']));
            } else {
                $ids = $payload['ids'];
            }

            $criteria->setIds($ids);
            $criteria->setLimit(null);
        } else {
            if (isset($payload['total-count-mode'])) {
                $this->addTotalCountMode((string) $payload['total-count-mode'], $criteria);
            }

            if (isset($payload['limit'])) {
                $this->addLimit($payload, $criteria, $searchException, $maxLimit);
            }

            if ($criteria->getLimit() === null && $maxLimit !== null) {
                $criteria->setLimit($maxLimit);
            }

            if (isset($payload['page'])) {
                $this->setPage($payload, $criteria, $searchException);
            }
        }

        if (isset($payload['includes'])) {
            $criteria->setIncludes($payload['includes']);
        }

        if (isset($payload['filter'])) {
            $this->addFilter($definition, $payload, $criteria, $searchException);
        }

        if (isset($payload['grouping'])) {
            foreach ($payload['grouping'] as $groupField) {
                $criteria->addGroupField(new FieldGrouping($groupField));
            }
        }

        if (isset($payload['post-filter'])) {
            $this->addPostFilter($definition, $payload, $criteria, $searchException);
        }

        if (isset($payload['query']) && \is_array($payload['query'])) {
            foreach ($payload['query'] as $query) {
                $parsedQuery = QueryStringParser::fromArray($definition, $query['query'], $searchException);
                $score = $query['score'] ?? 1;
                $scoreField = $query['scoreField'] ?? null;

                $criteria->addQuery(new ScoreQuery($parsedQuery, $score, $scoreField));
            }
        }

        if (isset($payload['term'])) {
            $term = trim((string) $payload['term']);
            $criteria->setTerm($term);
        }

        if (isset($payload['sort'])) {
            $this->addSorting($payload, $criteria, $definition, $searchException);
        }

        if (isset($payload['aggregations'])) {
            $this->aggregationParser->buildAggregations($definition, $payload, $criteria, $searchException);
        }

        if (isset($payload['associations'])) {
            foreach ($payload['associations'] as $propertyName => $association) {
                $field = $definition->getFields()->get($propertyName);

                if (!$field instanceof AssociationField) {
                    throw new AssociationNotFoundException($propertyName);
                }

                $ref = $field->getReferenceDefinition();
                if ($field instanceof ManyToManyAssociationField) {
                    $ref = $field->getToManyReferenceDefinition();
                }

                $nested = $criteria->getAssociation($propertyName);

                $this->parse($association, $nested, $ref, $context, null);

                if ($field instanceof TranslationsAssociationField) {
                    $nested->setLimit(null);
                }
            }
        }

        if (isset($payload['fields'])) {
            $criteria->addFields($payload['fields']);
        }

        $searchException->tryToThrow();

        $this->validator->validate($definition->getEntityName(), $criteria, $context);

        return $criteria;
    }

    private function parseSorting(EntityDefinition $definition, array $sorting): array
    {
        $sortings = [];
        foreach ($sorting as $sort) {
            $order = $sort['order'] ?? 'asc';
            $naturalSorting = $sort['naturalSorting'] ?? false;
            $type = $sort['type'] ?? '';

            if (strcasecmp((string) $order, 'desc') === 0) {
                $order = FieldSorting::DESCENDING;
            } else {
                $order = FieldSorting::ASCENDING;
            }

            $class = strcasecmp((string) $type, 'count') === 0 ? CountSorting::class : FieldSorting::class;

            $sortings[] = new $class(
                $this->buildFieldName($definition, $sort['field']),
                $order,
                (bool) $naturalSorting
            );
        }

        return $sortings;
    }

    private function parseSimpleSorting(EntityDefinition $definition, string $query): array
    {
        $parts = array_filter(explode(',', $query));

        if (empty($parts)) {
            throw new InvalidSortQueryException();
        }

        $sorting = [];
        foreach ($parts as $part) {
            $first = mb_substr($part, 0, 1);

            $direction = $first === '-' ? FieldSorting::DESCENDING : FieldSorting::ASCENDING;

            if ($direction === FieldSorting::DESCENDING) {
                $part = mb_substr($part, 1);
            }

            $sorting[] = new FieldSorting($this->buildFieldName($definition, $part), $direction);
        }

        return $sorting;
    }

    private function parseSimpleFilter(EntityDefinition $definition, array $filters, SearchRequestException $searchRequestException): MultiFilter
    {
        $queries = [];

        $index = -1;
        foreach ($filters as $field => $value) {
            ++$index;

            if ($field === '') {
                $searchRequestException->add(new InvalidFilterQueryException(sprintf('The key for filter at position "%d" must not be blank.', $index)), '/filter/' . $index);

                continue;
            }

            if ($value === '') {
                $searchRequestException->add(new InvalidFilterQueryException(sprintf('The value for filter "%s" must not be blank.', $field)), '/filter/' . $field);

                continue;
            }

            $queries[] = new EqualsFilter($this->buildFieldName($definition, $field), $value);
        }

        return new MultiFilter(MultiFilter::CONNECTION_AND, $queries);
    }

    private function setPage(array $payload, Criteria $criteria, SearchRequestException $searchRequestException): void
    {
        if ($payload['page'] === '') {
            $searchRequestException->add(new InvalidPageQueryException('(empty)'), '/page');

            return;
        }

        if (!is_numeric($payload['page'])) {
            $searchRequestException->add(new InvalidPageQueryException($payload['page']), '/page');

            return;
        }

        $page = (int) $payload['page'];
        $limit = (int) ($payload['limit'] ?? 0);

        if ($page <= 0) {
            $searchRequestException->add(new InvalidPageQueryException($page), '/page');

            return;
        }

        $offset = $limit * ($page - 1);
        $criteria->setOffset($offset);
    }

    private function addLimit(array $payload, Criteria $criteria, SearchRequestException $searchRequestException, ?int $maxLimit): void
    {
        if ($payload['limit'] === '') {
            $searchRequestException->add(new InvalidLimitQueryException('(empty)'), '/limit');

            return;
        }

        if (!is_numeric($payload['limit'])) {
            $searchRequestException->add(new InvalidLimitQueryException($payload['limit']), '/limit');

            return;
        }

        $limit = (int) $payload['limit'];
        if ($limit <= 0) {
            $searchRequestException->add(new InvalidLimitQueryException($limit), '/limit');

            return;
        }

        if ($maxLimit > 0 && $limit > $maxLimit) {
            $searchRequestException->add(new QueryLimitExceededException($this->maxLimit, $limit), '/limit');

            return;
        }

        $criteria->setLimit($limit);
    }

    private function addFilter(EntityDefinition $definition, array $payload, Criteria $criteria, SearchRequestException $searchException): void
    {
        if (!\is_array($payload['filter'])) {
            $searchException->add(new InvalidFilterQueryException('The filter parameter has to be a list of filters.'), '/filter');

            return;
        }

        if ($this->hasNumericIndex($payload['filter'])) {
            foreach ($payload['filter'] as $index => $query) {
                try {
                    $filter = QueryStringParser::fromArray($definition, $query, $searchException, '/filter/' . $index);
                    $criteria->addFilter($filter);
                } catch (InvalidFilterQueryException $ex) {
                    $searchException->add($ex, $ex->getPath());
                }
            }

            return;
        }

        $criteria->addFilter($this->parseSimpleFilter($definition, $payload['filter'], $searchException));
    }

    private function addPostFilter(EntityDefinition $definition, array $payload, Criteria $criteria, SearchRequestException $searchException): void
    {
        if (!\is_array($payload['post-filter'])) {
            $searchException->add(new InvalidFilterQueryException('The filter parameter has to be a list of filters.'), '/post-filter');

            return;
        }

        if ($this->hasNumericIndex($payload['post-filter'])) {
            foreach ($payload['post-filter'] as $index => $query) {
                try {
                    $filter = QueryStringParser::fromArray($definition, $query, $searchException, '/post-filter/' . $index);
                    $criteria->addPostFilter($filter);
                } catch (InvalidFilterQueryException $ex) {
                    $searchException->add($ex, $ex->getPath());
                }
            }

            return;
        }

        $criteria->addPostFilter(
            $this->parseSimpleFilter(
                $definition,
                $payload['post-filter'],
                $searchException
            )
        );
    }

    private function hasNumericIndex(array $data): bool
    {
        return array_keys($data) === range(0, \count($data) - 1);
    }

    private function addSorting(array $payload, Criteria $criteria, EntityDefinition $definition, SearchRequestException $searchException): void
    {
        if (\is_array($payload['sort'])) {
            $sorting = $this->parseSorting($definition, $payload['sort']);
            $criteria->addSorting(...$sorting);

            return;
        }

        try {
            $sorting = $this->parseSimpleSorting($definition, $payload['sort']);
            $criteria->addSorting(...$sorting);
        } catch (InvalidSortQueryException $ex) {
            $searchException->add($ex, '/sort');
        }
    }

    private function buildFieldName(EntityDefinition $definition, string $fieldName): string
    {
        if ($fieldName === '_score') {
            // Do not prefix _score fields because they are not actual entity properties but a calculated field in the
            // SQL selection.
            return $fieldName;
        }

        $prefix = $definition->getEntityName() . '.';

        if (mb_strpos($fieldName, $prefix) === false) {
            return $prefix . $fieldName;
        }

        return $fieldName;
    }
}
