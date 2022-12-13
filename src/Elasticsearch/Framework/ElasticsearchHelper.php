<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use OpenSearch\Client;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Search;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Elasticsearch\Exception\ServerNotAvailableException;
use Shopware\Elasticsearch\Exception\UnsupportedElasticsearchDefinitionException;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;

/**
 * @package core
 */
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

    /**
     * @internal
     */
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

    /**
     * @deprecated tag:v6.5.0 - use logAndThrowException instead
     */
    public function logOrThrowException(\Throwable $exception): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'logAndThrowException()')
        );

        return $this->logAndThrowException($exception);
    }

    public function logAndThrowException(\Throwable $exception): bool
    {
        $this->logger->critical($exception->getMessage());

        if ($this->environment === 'test' || $this->throwException) {
            throw $exception;
        }

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
            return $this->logAndThrowException(new ServerNotAvailableException());
        }

        return true;
    }

    /**
     * Validates if it is allowed do execute the search request over elasticsearch
     *
     * @deprecated tag:v6.5.0 - Parameter $criteria will be required
     */
    public function allowSearch(EntityDefinition $definition, Context $context/*, Criteria $criteria */): bool
    {
        /** @var Criteria|null $criteria */
        $criteria = \func_num_args() >= 3 ? func_get_arg(2) : null;

        if ($criteria === null) {
            Feature::triggerDeprecationOrThrow('v6.5.0.0', 'The parameter $criteria is required in allowSearch');
        }

        if (!$this->searchEnabled) {
            return false;
        }

        if (!$this->isSupported($definition)) {
            return false;
        }

        // @deprecated tag:v6.5.0 whole if block can be removed
        if ($criteria === null || !$criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE)) {
            return $context->hasState(Context::STATE_ELASTICSEARCH_AWARE);
        }

        return $criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE);
    }

    public function handleIds(EntityDefinition $definition, Criteria $criteria, Search $search, Context $context): void
    {
        $ids = $criteria->getIds();

        if (empty($ids)) {
            return;
        }

        /** @var list<string> $ids */
        $ids = array_values($ids);

        $query = $this->parser->parseFilter(
            new EqualsAnyFilter('id', $ids),
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
