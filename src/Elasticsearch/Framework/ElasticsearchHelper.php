<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Search;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

class ElasticsearchHelper
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var DefinitionRegistry
     */
    private $registry;

    /**
     * @var CriteriaParser
     */
    private $parser;

    public function __construct(
        Client $client,
        DefinitionRegistry $registry,
        CriteriaParser $parser
    ) {
        $this->client = $client;
        $this->registry = $registry;
        $this->parser = $parser;
    }

    /**
     * Validates if it is allowed do execute the search request over elasticsearch
     */
    public function allowSearch(EntityDefinition $definition, Context $context): bool
    {
        if (!$this->registry->isSupported($definition)) {
            return false;
        }

        // while indexing or not cacheable call?
        if (!$context->getUseCache()) {
            return false;
        }

        if (!$this->hasIndexDocuments($definition, $context)) {
            throw new \RuntimeException(sprintf('No indexed documents found for entity %s', $definition->getEntityName()));
        }

        return true;
    }

    public function hasIndexDocuments(EntityDefinition $definition, Context $context): bool
    {
        $index = $this->registry->getIndex($definition, $context);

        $exists = $this->client->indices()->exists(['index' => $index]);
        if (!$exists) {
            return false;
        }

        $count = $this->client->count(['index' => $index]);
        if (!array_key_exists('count', $count)) {
            return false;
        }

        return $count['count'] > 0;
    }

    public function addFilters(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        $filters = $criteria->getFilters();
        if (empty($filters)) {
            return;
        }

        $query = $this->parser->parse(
            new MultiFilter(MultiFilter::CONNECTION_AND, $filters),
            $definition,
            $definition->getEntityName(),
            $context
        );

        $search->addQuery($query, BoolQuery::FILTER);
    }

    public function addPostFilters(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        $postFilters = $criteria->getPostFilters();
        if (empty($postFilters)) {
            return;
        }

        $query = $this->parser->parse(
            new MultiFilter(MultiFilter::CONNECTION_AND, $postFilters),
            $definition,
            $definition->getEntityName(),
            $context
        );

        $search->addPostFilter($query, BoolQuery::FILTER);
    }

    public function addQueries(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        $queries = $criteria->getQueries();
        if (empty($queries)) {
            return;
        }

        $bool = new BoolQuery();

        foreach ($queries as $query) {
            $parsed = $this->parser->parse($query->getQuery(), $definition, $definition->getEntityName(), $context);

            if ($parsed instanceof MatchQuery) {
                $score = (string) ($query->getScore() / 10);
                $parsed->addParameter('boost', $score);
            }

            $bool->add($parsed, BoolQuery::SHOULD);
        }
        $bool->addParameter('minimum_should_match', '1');
        $search->addQuery($bool, BoolQuery::SHOULD);
    }

    public function addSortings(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        foreach ($criteria->getSorting() as $sorting) {
            $search->addSort(
                $this->parser->parseSorting($sorting, $definition, $context)
            );
        }
    }

    public function addAggregations(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        $aggregations = $criteria->getAggregations();
        if (empty($aggregations)) {
            return;
        }

        foreach ($aggregations as $aggregation) {
            $agg = $this->parser->parseAggregation($aggregation, $definition, $context);

            if (!$agg) {
                continue;
            }

            $search->addAggregation($agg);
        }
    }
}
