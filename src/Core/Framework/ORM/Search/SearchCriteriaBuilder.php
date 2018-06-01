<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search;

use Shopware\Framework\Context;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Search\Aggregation\AvgAggregation;
use Shopware\Framework\ORM\Search\Aggregation\CardinalityAggregation;
use Shopware\Framework\ORM\Search\Aggregation\CountAggregation;
use Shopware\Framework\ORM\Search\Aggregation\MaxAggregation;
use Shopware\Framework\ORM\Search\Aggregation\MinAggregation;
use Shopware\Framework\ORM\Search\Aggregation\StatsAggregation;
use Shopware\Framework\ORM\Search\Aggregation\SumAggregation;
use Shopware\Framework\ORM\Search\Aggregation\ValueCountAggregation;
use Shopware\Framework\ORM\Search\Parser\QueryStringParser;
use Shopware\Framework\ORM\Search\Query\NestedQuery;
use Shopware\Framework\ORM\Search\Query\ScoreQuery;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Framework\ORM\Search\Sorting\FieldSorting;
use Shopware\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Framework\ORM\Search\Term\SearchTermInterpreter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SearchCriteriaBuilder
{
    /**
     * @var SearchTermInterpreter
     */
    private $searchTermInterpreter;

    /**
     * @var EntityScoreQueryBuilder
     */
    private $entityScoreQueryBuilder;

    public function __construct(
        SearchTermInterpreter $searchTermInterpreter,
        EntityScoreQueryBuilder $entityScoreQueryBuilder
    ) {
        $this->searchTermInterpreter = $searchTermInterpreter;
        $this->entityScoreQueryBuilder = $entityScoreQueryBuilder;
    }

    public function handleRequest(Request $request, Criteria $criteria, string $definition, Context $context): Criteria
    {
        switch ($request->getMethod()) {
            case 'POST':
                $payload = json_decode($request->getContent(), true);
                if (!is_array($payload)) {
                    throw new BadRequestHttpException('Malformed JSON');
                }

                return $this->fromArray($payload, $criteria, $definition, $context);
            case 'GET':
                $payload = $request->query->all();

                return $this->fromArray($payload, $criteria, $definition, $context);
        }

        return $criteria;
    }

    private function fromArray(array $payload, Criteria $criteria, string $definition, Context $context): Criteria
    {
        $criteria->setFetchCount(Criteria::FETCH_COUNT_TOTAL);
        $criteria->setLimit(10);

        if (isset($payload['fetch-count'])) {
            $criteria->setFetchCount((int) $payload['fetch-count']);
        }
        if (isset($payload['offset'])) {
            $criteria->setOffset((int) $payload['offset']);
        }
        if (isset($payload['limit'])) {
            $criteria->setLimit((int) $payload['limit']);
        }

        if (isset($payload['filter']) && is_array($payload['filter'])) {
            foreach ($payload['filter'] as $query) {
                if (is_array($query)) {
                    $filter = QueryStringParser::fromArray($query);
                } else {
                    $filter = $this->parseSimpleFilter($payload['filter']);
                }
                $criteria->addFilter($filter);
            }
        }

        if (isset($payload['post-filter']) && is_array($payload['post-filter'])) {
            foreach ($payload['post-filter'] as $query) {
                if (is_array($query)) {
                    $filter = QueryStringParser::fromArray($query);
                } else {
                    $filter = $this->parseSimpleFilter($payload['post-filter']);
                }
                $criteria->addPostFilter($filter);
            }
        }

        if (isset($payload['query']) && is_array($payload['query'])) {
            foreach ($payload['query'] as $query) {
                $parsedQuery = QueryStringParser::fromArray($query['query']);
                $score = isset($query['score']) ? $query['score'] : 1;
                $scoreField = isset($query['scoreField']) ? $query['scoreField'] : null;

                $criteria->addQuery(new ScoreQuery($parsedQuery, $score, $scoreField));
            }
        }

        if (isset($payload['term'])) {
            $pattern = $this->searchTermInterpreter->interpret(
                (string) $payload['term'],
                $context
            );

            /** @var EntityDefinition|string $definition */
            $queries = $this->entityScoreQueryBuilder->buildScoreQueries(
                $pattern,
                $definition,
                $definition::getEntityName()
            );

            $criteria->addQueries($queries);
        }

        if (isset($payload['sort'])) {
            if (is_array($payload['sort'])) {
                $criteria->addSortings($this->parseSorting($payload['sort']));
            } else {
                $criteria->addSortings($this->parseSimpleSorting($definition, $payload['sort']));
            }
        }

        if (isset($payload['aggregations'])) {
            $this->buildAggregations($payload, $criteria);
        }

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

        $sortings = [];
        foreach ($parts as $part) {
            $first = substr($part, 0, 1);

            $direction = $first === '-' ? FieldSorting::DESCENDING : FieldSorting::ASCENDING;

            if ($direction === FieldSorting::DESCENDING) {
                $part = substr($part, 1);
            }

            $subParts = explode('.', $part);

            /** @var string|EntityDefinition $definition */
            $root = $definition::getEntityName();

            if ($subParts[0] !== $root) {
                $part = $definition::getEntityName() . '.' . $part;
            }

            $sortings[] = new FieldSorting($part, $direction);
        }

        return $sortings;
    }

    private function parseSimpleFilter(array $filters): NestedQuery
    {
        $queries = [];
        foreach ($filters as $field => $value) {
            $queries[] = new TermQuery($field, $value);
        }

        return new NestedQuery($queries);
    }

    private function buildAggregations(array $payload, Criteria $criteria): void
    {
        foreach ($payload['aggregations'] as $name => $aggregations) {
            foreach ($aggregations as $type => $aggregation) {
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
                }
            }
        }
    }
}
