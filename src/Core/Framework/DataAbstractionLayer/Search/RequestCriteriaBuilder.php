<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\AssociationNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DisallowedLimitQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidLimitQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidPageQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSortQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\QueryLimitExceededException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\HttpFoundation\Request;

class RequestCriteriaBuilder
{
    /**
     * @var int
     */
    private $maxLimit;

    /**
     * @var int[]
     */
    private $allowedLimits;

    /**
     * @var AggregationParser
     */
    private $aggregationParser;

    public function __construct(AggregationParser $aggregationParser, int $maxLimit, array $availableLimits = [])
    {
        $this->maxLimit = $maxLimit;
        $this->allowedLimits = $availableLimits;
        $this->aggregationParser = $aggregationParser;
    }

    public function handleRequest(Request $request, Criteria $criteria, EntityDefinition $definition, Context $context): Criteria
    {
        if ($request->getMethod() === Request::METHOD_GET) {
            $criteria = $this->fromArray($request->query->all(), $criteria, $definition, $context);
        } else {
            $criteria = $this->fromArray($request->request->all(), $criteria, $definition, $context);
        }

        if (empty($criteria->getIds()) && $criteria->getLimit() === null) {
            $criteria->setLimit(10);
        }

        return $criteria;
    }

    public function getMaxLimit(): int
    {
        return $this->maxLimit;
    }

    public function getAllowedLimits(): array
    {
        return $this->allowedLimits;
    }

    private function fromArray(array $payload, Criteria $criteria, EntityDefinition $definition, Context $context): Criteria
    {
        $searchException = new SearchRequestException();

        if (isset($payload['ids'])) {
            $ids = array_filter(explode('|', $payload['ids']));
            $criteria->setIds($ids);
            $criteria->setLimit(null);
        } else {
            $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

            if (isset($payload['total-count-mode'])) {
                $criteria->setTotalCountMode((int) $payload['total-count-mode']);
            }

            if (isset($payload['limit'])) {
                $this->addLimit($payload, $criteria, $searchException);
            }

            if (isset($payload['page'])) {
                $this->setPage($payload, $criteria, $searchException);
            }
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

                $this->fromArray($association, $nested, $ref, $context);
            }
        }

        $searchException->tryToThrow();

        return $criteria;
    }

    private function parseSorting(EntityDefinition $definition, array $sorting): array
    {
        $sortings = [];
        foreach ($sorting as $sort) {
            $order = $sort['order'] ?? 'asc';
            $naturalSorting = $sort['naturalSorting'] ?? false;

            if (strcasecmp($order, 'desc') === 0) {
                $order = FieldSorting::DESCENDING;
            } else {
                $order = FieldSorting::ASCENDING;
            }

            $sortings[] = new FieldSorting(
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
            $first = substr($part, 0, 1);

            $direction = $first === '-' ? FieldSorting::DESCENDING : FieldSorting::ASCENDING;

            if ($direction === FieldSorting::DESCENDING) {
                $part = substr($part, 1);
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
                $searchRequestException->add(new InvalidFilterQueryException(sprintf('The key for filter at position "%s" must not be blank.', $index)), '/filter/' . $index);
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

    private function addLimit(array $payload, Criteria $criteria, SearchRequestException $searchRequestException): void
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

        if (empty($this->allowedLimits) && $this->maxLimit > 0 && $limit > $this->maxLimit) {
            $searchRequestException->add(new QueryLimitExceededException($this->maxLimit, $limit), '/limit');

            return;
        }

        if (!empty($this->allowedLimits) && !\in_array($limit, $this->allowedLimits, true)) {
            $searchRequestException->add(new DisallowedLimitQueryException($this->allowedLimits, $limit), '/limit');

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
        $prefix = $definition->getEntityName() . '.';

        if (strpos($fieldName, $prefix) === false) {
            return $prefix . $fieldName;
        }

        return $fieldName;
    }
}
