<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Search\Parser\QueryStringParser;
use Shopware\Api\Entity\Search\Query\ScoreQuery;
use Shopware\Api\Entity\Search\Sorting\FieldSorting;
use Shopware\Api\Entity\Search\Term\EntityScoreQueryBuilder;
use Shopware\Api\Entity\Search\Term\SearchTermInterpreter;
use Shopware\Context\Struct\ShopContext;
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

    public function handleRequest(Request $request, string $definition, ShopContext $context): Criteria
    {
        switch ($request->getMethod()) {
            case 'POST':
                $payload = json_decode($request->getContent(), true);
                if (!$payload) {
                    throw new BadRequestHttpException('Malformed JSON');
                }

                return $this->fromArray($payload, $definition, $context);
        }

        return new Criteria();
    }

    private function fromArray(array $payload, string $definition, ShopContext $context): Criteria
    {
        $criteria = new Criteria();
        $criteria->setFetchCount(Criteria::FETCH_COUNT_TOTAL);
        $criteria->setLimit(10);

        if (isset($payload['fetch-count'])) {
            $criteria->setFetchCount((int) $payload['fetch-count']);
        }
        if (isset($payload['offset'])) {
            $criteria->setOffset((int) $payload['offset']);
        }
        if (isset($payload['size'])) {
            $criteria->setLimit((int) $payload['size']);
        }

        if (isset($payload['filter']) && is_array($payload['filter'])) {
            foreach ($payload['filter'] as $query) {
                $criteria->addFilter(
                    QueryStringParser::fromArray($query)
                );
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

        if (isset($payload['sort']) && is_array($payload['sort'])) {
            $criteria->addSortings($this->parseSorting($payload['sort']));
        }

        return $criteria;
    }

    private function parseSorting(array $sorting): array
    {
        $sortings = [];
        foreach ($sorting as $sort) {
            if (strcasecmp($sort['order'], 'desc') === 0) {
                $order = FieldSorting::DESCENDING;
            } else {
                $order = FieldSorting::ASCENDING;
            }
            $sortings[] = new FieldSorting($sort['field'], $order);
        }

        return $sortings;
    }
}
