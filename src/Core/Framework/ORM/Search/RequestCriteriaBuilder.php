<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Search;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Exception\InvalidAggregationQueryException;
use Shopware\Core\Framework\ORM\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\ORM\Exception\InvalidLimitQueryException;
use Shopware\Core\Framework\ORM\Exception\InvalidOffsetQueryException;
use Shopware\Core\Framework\ORM\Exception\InvalidSortQueryException;
use Shopware\Core\Framework\ORM\Exception\SearchRequestException;
use Shopware\Core\Framework\ORM\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\CardinalityAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\CountAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\MaxAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\MinAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\ORM\Search\Aggregation\ValueCountAggregation;
use Shopware\Core\Framework\ORM\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\ORM\Search\Query\NestedQuery;
use Shopware\Core\Framework\ORM\Search\Query\ScoreQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Sorting\FieldSorting;
use Symfony\Component\HttpFoundation\Request;

class RequestCriteriaBuilder
{
    /**
     * @var SearchBuilder
     */
    private $searchBuilder;

    public function __construct(SearchBuilder $searchBuilder)
    {
        $this->searchBuilder = $searchBuilder;
    }

    public function handleRequest(Request $request, Criteria $criteria, string $definition, Context $context): Criteria
    {
        if ($request->getMethod() === Request::METHOD_GET) {
            return $this->fromArray($request->query->all(), $criteria, $definition, $context);
        }

        return $this->fromArray($request->request->all(), $criteria, $definition, $context);
    }

    private function fromArray(array $payload, Criteria $criteria, string $definition, Context $context): Criteria
    {
        $searchException = new SearchRequestException();

        $criteria->setFetchCount(Criteria::FETCH_COUNT_TOTAL);
        $criteria->setLimit(10);

        if (isset($payload['fetch-count'])) {
            $criteria->setFetchCount((int) $payload['fetch-count']);
        }

        if (isset($payload['offset'])) {
            $this->addOffset($payload, $criteria, $searchException);
        }

        if (isset($payload['limit'])) {
            $this->addLimit($payload, $criteria, $searchException);
        }

        if (isset($payload['filter'])) {
            $this->addFilter($payload, $criteria, $searchException);
        }

        if (isset($payload['post-filter'])) {
            $this->addPostFilter($payload, $criteria, $searchException);
        }

        if (isset($payload['query']) && is_array($payload['query'])) {
            foreach ($payload['query'] as $query) {
                $parsedQuery = QueryStringParser::fromArray($query['query'], $searchException);
                $score = $query['score'] ?? 1;
                $scoreField = $query['scoreField'] ?? null;

                $criteria->addQuery(new ScoreQuery($parsedQuery, $score, $scoreField));
            }
        }

        if (isset($payload['term'])) {
            $term = trim((string) $payload['term']);

            $this->searchBuilder->build($criteria, $term, $definition, $context);
        }

        if (isset($payload['sort'])) {
            $this->addSorting($payload, $criteria, $definition, $searchException);
        }

        if (isset($payload['aggregations'])) {
            $this->buildAggregations($payload, $criteria, $searchException);
        }

        $searchException->tryToThrow();

        return $criteria;
    }

    private function parseSorting(array $sorting): array
    {
        $sortings = [];
        foreach ($sorting as $sort) {
            $order = $sort['order'] ?? 'asc';

            if (strcasecmp($order, 'desc') === 0) {
                $order = FieldSorting::DESCENDING;
            } else {
                $order = FieldSorting::ASCENDING;
            }

            $sortings[] = new FieldSorting($sort['field'], $order);
        }

        return $sortings;
    }

    private function parseSimpleSorting(string $definition, string $query): array
    {
        $parts = array_filter(explode(',', $query));

        if (empty($parts)) {
            throw new InvalidSortQueryException('A value for the sort parameter is required.');
        }

        $sorting = [];
        foreach ($parts as $part) {
            $first = substr($part, 0, 1);

            $direction = $first === '-' ? FieldSorting::DESCENDING : FieldSorting::ASCENDING;

            if ($direction === FieldSorting::DESCENDING) {
                $part = substr($part, 1);
            }

            $sorting[] = new FieldSorting($part, $direction);
        }

        return $sorting;
    }

    private function parseSimpleFilter(array $filters, SearchRequestException $searchRequestException): NestedQuery
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

            $queries[] = new TermQuery($field, $value);
        }

        return new NestedQuery($queries);
    }

    private function buildAggregations(array $payload, Criteria $criteria, SearchRequestException $searchRequestException): void
    {
        if (!is_array($payload['aggregations'])) {
            throw new InvalidAggregationQueryException('The aggregations parameter has to be a list of aggregations.');
        }

        $index = 0;
        foreach ($payload['aggregations'] as $name => $aggregations) {
            if (empty($name) || is_numeric($name)) {
                $searchRequestException->add(new InvalidAggregationQueryException('The aggregation field key should be a non-empty string.'), '/aggregations/' . $index);
                continue;
            }

            if (!is_array($aggregations)) {
                $searchRequestException->add(new InvalidAggregationQueryException('The field "%s" should be a list of aggregations.'), '/aggregations/' . $name);
                continue;
            }

            $subIndex = 0;
            foreach ($aggregations as $type => $aggregation) {
                if (empty($type) || is_numeric($type)) {
                    $searchRequestException->add(new InvalidAggregationQueryException('The aggregations of "%s" should be a non-empty string.'), '/aggregations/' . $name . '/' . $subIndex);
                    continue;
                }

                if (empty($aggregation['field'])) {
                    $searchRequestException->add(new InvalidAggregationQueryException('The aggregation should contain a "field".'), '/aggregations/' . $name . '/' . $type . '/field');
                    continue;
                }

                $field = $aggregation['field'];
                switch ($type) {
                    case 'avg':
                        $criteria->addAggregation(new AvgAggregation($field, $name));
                        break;

                    case 'cardinality':
                        $criteria->addAggregation(new CardinalityAggregation($field, $name));
                        break;

                    case 'count':
                        $criteria->addAggregation(new CountAggregation($field, $name));
                        break;

                    case 'max':
                        $criteria->addAggregation(new MaxAggregation($field, $name));
                        break;

                    case 'min':
                        $criteria->addAggregation(new MinAggregation($field, $name));
                        break;

                    case 'stats':
                        $criteria->addAggregation(new StatsAggregation($field, $name));
                        break;

                    case 'sum':
                        $criteria->addAggregation(new SumAggregation($field, $name));
                        break;

                    case 'value_count':
                        $criteria->addAggregation(new ValueCountAggregation($field, $name));
                        break;

                    default:
                        $searchRequestException->add(new InvalidAggregationQueryException(sprintf('The aggregation type "%s" used as key does not exists.', $type)), '/aggregations/' . $name);
                }

                ++$subIndex;
            }

            ++$index;
        }
    }

    private function addOffset(array $payload, Criteria $criteria, SearchRequestException $searchRequestException): void
    {
        if ($payload['offset'] === '') {
            $searchRequestException->add(new InvalidOffsetQueryException('(empty)'), '/offset');

            return;
        }

        if (!is_numeric($payload['offset'])) {
            $searchRequestException->add(new InvalidOffsetQueryException($payload['offset']), '/offset');

            return;
        }

        $offset = (int) $payload['offset'];
        if ($offset < 0) {
            $searchRequestException->add(new InvalidOffsetQueryException($offset), '/offset');

            return;
        }

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

        $criteria->setLimit($limit);
    }

    private function addFilter(array $payload, Criteria $criteria, SearchRequestException $searchException): void
    {
        if (!is_array($payload['filter'])) {
            $searchException->add(new InvalidFilterQueryException('The filter parameter has to be a list of filters.'), '/filter');

            return;
        }

        if ($this->hasNumericIndex($payload['filter'])) {
            foreach ($payload['filter'] as $index => $query) {
                try {
                    $filter = QueryStringParser::fromArray($query, $searchException, '/filter/' . $index);
                    $criteria->addFilter($filter);
                } catch (InvalidFilterQueryException $ex) {
                    $searchException->add($ex, $ex->getPath());
                }
            }

            return;
        }

        $criteria->addFilter($this->parseSimpleFilter($payload['filter'], $searchException));
    }

    private function addPostFilter(array $payload, Criteria $criteria, SearchRequestException $searchException): void
    {
        if (!is_array($payload['post-filter'])) {
            $searchException->add(new InvalidFilterQueryException('The filter parameter has to be a list of filters.'), '/post-filter');

            return;
        }

        if ($this->hasNumericIndex($payload['post-filter'])) {
            foreach ($payload['post-filter'] as $index => $query) {
                try {
                    $filter = QueryStringParser::fromArray($query, $searchException, '/post-filter/' . $index);
                    $criteria->addPostFilter($filter);
                } catch (InvalidFilterQueryException $ex) {
                    $searchException->add($ex, $ex->getPath());
                }
            }

            return;
        }

        $criteria->addPostFilter($this->parseSimpleFilter($payload['post-filter'], $searchException));
    }

    private function hasNumericIndex(array $data): bool
    {
        return array_keys($data) === range(0, count($data) - 1);
    }

    private function addSorting(array $payload, Criteria $criteria, string $definition, SearchRequestException $searchException): void
    {
        if (is_array($payload['sort'])) {
            $criteria->addSortings($this->parseSorting($payload['sort']));

            return;
        }

        try {
            $sorting = $this->parseSimpleSorting($definition, $payload['sort']);
            $criteria->addSortings($sorting);
        } catch (InvalidSortQueryException $ex) {
            $searchException->add($ex, '/sort');
        }
    }
}
