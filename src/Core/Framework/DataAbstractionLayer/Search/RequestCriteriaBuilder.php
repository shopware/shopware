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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
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
     * @var AggregationParser
     */
    private $aggregationParser;

    /**
     * @var ApiCriteriaValidator
     */
    private $validator;

    public function __construct(
        AggregationParser $aggregationParser,
        int $maxLimit,
        ApiCriteriaValidator $validator
    ) {
        $this->maxLimit = $maxLimit;
        $this->aggregationParser = $aggregationParser;
        $this->validator = $validator;
    }

    public function handleRequest(Request $request, Criteria $criteria, EntityDefinition $definition, Context $context): Criteria
    {
        if ($request->getMethod() === Request::METHOD_GET) {
            $criteria = $this->fromArray($request->query->all(), $criteria, $definition, $context, $request->attributes->getInt('version'));
        } else {
            $criteria = $this->fromArray($request->request->all(), $criteria, $definition, $context, $request->attributes->getInt('version'));
        }

        $this->validator->validate($definition->getEntityName(), $criteria, $context);

        return $criteria;
    }

    public function getMaxLimit(): int
    {
        return $this->maxLimit;
    }

    public function toArray(Criteria $criteria): array
    {
        $array = [
            'total-count-mode' => $criteria->getTotalCountMode(),
        ];

        if ($criteria->getLimit()) {
            $array['limit'] = $criteria->getLimit();
        }

        if ($criteria->getOffset()) {
            $array['offset'] = $criteria->getOffset();
        }

        if ($criteria->getTerm()) {
            $array['term'] = $criteria->getTerm();
        }

        if ($criteria->getIncludes()) {
            $array['includes'] = $criteria->getIncludes();
        }

        if (\count($criteria->getIds())) {
            $array['ids'] = $criteria->getIds();
        }

        if (\count($criteria->getFilters())) {
            $array['filter'] = array_map(static function (Filter $filter) {
                return QueryStringParser::toArray($filter);
            }, $criteria->getFilters());
        }

        if (\count($criteria->getPostFilters())) {
            $array['post-filter'] = array_map(static function (Filter $filter) {
                return QueryStringParser::toArray($filter);
            }, $criteria->getPostFilters());
        }

        if (\count($criteria->getAssociations())) {
            foreach ($criteria->getAssociations() as $assocName => $association) {
                $array['associations'][$assocName] = $this->toArray($association);
            }
        }

        if (\count($criteria->getSorting())) {
            $array['sort'] = json_decode(json_encode($criteria->getSorting()), true);

            foreach ($array['sort'] as &$sort) {
                $sort['order'] = $sort['direction'];
                unset($sort['direction']);
            }
            unset($sort);
        }

        if (\count($criteria->getQueries())) {
            $array['query'] = [];

            foreach ($criteria->getQueries() as $query) {
                $arrayQuery = json_decode(json_encode($query), true);
                $arrayQuery['query'] = QueryStringParser::toArray($query->getQuery());
                $array['query'][] = $arrayQuery;
            }
        }

        if (\count($criteria->getGroupFields())) {
            $array['grouping'] = [];

            foreach ($criteria->getGroupFields() as $groupField) {
                $array['grouping'][] = $groupField->getField();
            }
        }

        if (\count($criteria->getAggregations())) {
            $array['aggregations'] = $this->aggregationParser->toArray($criteria->getAggregations());
        }

        return $array;
    }

    private function fromArray(array $payload, Criteria $criteria, EntityDefinition $definition, Context $context, int $apiVersion): Criteria
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
                $criteria->setTotalCountMode((int) $payload['total-count-mode']);
            }

            if (isset($payload['limit'])) {
                $this->addLimit($payload, $criteria, $searchException);
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

                $this->fromArray($association, $nested, $ref, $context, $apiVersion);
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

        if ($this->maxLimit > 0 && $limit > $this->maxLimit) {
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
