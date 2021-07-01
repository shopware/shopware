<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Search;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Elasticsearch\Exception\ServerNotAvailableException;
use Shopware\Elasticsearch\Exception\UnsupportedElasticsearchDefinitionException;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;

class ElasticsearchHelper
{
    // max for default configuration
    public const MAX_SIZE_VALUE = 10000;

    private Client $client;

    private ElasticsearchRegistry $registry;

    private CriteriaParser $parser;

    private bool $searchEnabled;

    private bool $indexingEnabled;

    private string $environment;

    private LoggerInterface $logger;

    private string $prefix;

    private bool $throwException;

    public function __construct(
        string $environment,
        bool $searchEnabled,
        bool $indexingEnabled,
        string $prefix,
        bool $throwException,
        Client $client,
        ElasticsearchRegistry $registry,
        CriteriaParser $parser,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->registry = $registry;
        $this->parser = $parser;
        $this->searchEnabled = $searchEnabled;
        $this->indexingEnabled = $indexingEnabled;
        $this->environment = $environment;
        $this->logger = $logger;
        $this->prefix = $prefix;
        $this->throwException = $throwException;
    }

    public function logOrThrowException(\Throwable $exception): bool
    {
        if ($this->environment === 'test') {
            throw $exception;
        }

        if ($this->environment !== 'dev') {
            return false;
        }

        if ($this->throwException) {
            throw $exception;
        }

        $this->logger->critical($exception->getMessage());

        return false;
    }

    /**
     * Created the index alias
     */
    public function getIndexName(EntityDefinition $definition, string $languageId): string
    {
        return $this->prefix . '_' . $definition->getEntityName() . '_' . $languageId;
    }

    public function allowIndexing(): bool
    {
        if (!$this->indexingEnabled) {
            return false;
        }

        if (!$this->client->ping()) {
            return $this->logOrThrowException(new ServerNotAvailableException());
        }

        return true;
    }

    /**
     * Validates if it is allowed do execute the search request over elasticsearch
     */
    public function allowSearch(EntityDefinition $definition, Context $context): bool
    {
        if (!$this->searchEnabled) {
            return false;
        }

        if (!$this->isSupported($definition)) {
            return false;
        }

        if (!$context->hasState(Context::STATE_ELASTICSEARCH_AWARE)) {
            return false;
        }

        return true;
    }

    public function handleIds(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        $ids = $criteria->getIds();

        if (empty($ids)) {
            return;
        }

        $query = $this->parser->parseFilter(
            new EqualsAnyFilter('id', array_values($ids)),
            $definition,
            $definition->getEntityName(),
            $context
        );

        $search->addQuery($query, BoolQuery::FILTER);
    }

    public function addFilters(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        $filters = $criteria->getFilters();
        if (empty($filters)) {
            return;
        }

        $query = $this->parser->parseFilter(
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

        $query = $this->parser->parseFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, $postFilters),
            $definition,
            $definition->getEntityName(),
            $context
        );

        $search->addPostFilter($query, BoolQuery::FILTER);
    }

    public function addTerm(Criteria $criteria, Search $search, Context $context, EntityDefinition $definition): void
    {
        if (!$criteria->getTerm()) {
            return;
        }

        $esDefinition = $this->registry->get($definition->getEntityName());

        if (!$esDefinition) {
            throw new UnsupportedElasticsearchDefinitionException($definition->getEntityName());
        }

        $query = $esDefinition->buildTermQuery($context, $criteria);

        $search->addQuery($query);
    }

    public function addQueries(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        $queries = $criteria->getQueries();
        if (empty($queries)) {
            return;
        }

        $bool = new BoolQuery();

        foreach ($queries as $query) {
            $parsed = $this->parser->parseFilter($query->getQuery(), $definition, $definition->getEntityName(), $context);

            if ($parsed instanceof MatchQuery) {
                $score = (string) $query->getScore();

                $parsed->addParameter('boost', $score);
                $parsed->addParameter('fuzziness', '2');
            }

            $bool->add($parsed, BoolQuery::SHOULD);
        }

        $bool->addParameter('minimum_should_match', '1');
        $search->addQuery($bool);
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

    /**
     * Only used for unit tests because the container parameter bag is frozen and can not be changed at runtime.
     * Therefore this function can be used to test different behaviours
     *
     * @internal
     */
    public function setEnabled(bool $enabled): self
    {
        $this->searchEnabled = $enabled;
        $this->indexingEnabled = $enabled;

        return $this;
    }

    public function isSupported(EntityDefinition $definition): bool
    {
        $entityName = $definition->getEntityName();

        return $this->registry->has($entityName);
    }
}
