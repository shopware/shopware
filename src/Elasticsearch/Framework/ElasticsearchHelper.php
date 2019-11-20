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
use Shopware\Elasticsearch\Exception\NoIndexedDocumentsException;
use Shopware\Elasticsearch\Exception\ServerNotAvailableException;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;

class ElasticsearchHelper
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var ElasticsearchRegistry
     */
    private $registry;

    /**
     * @var CriteriaParser
     */
    private $parser;

    /**
     * @var bool
     */
    private $searchEnabled;

    /**
     * @var bool
     */
    private $indexingEnabled;

    /**
     * @var string
     */
    private $environment;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $prefix;

    public function __construct(
        string $environment,
        bool $searchEnabled,
        bool $indexingEnabled,
        string $prefix,
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
    }

    public function logOrThrowException(\Throwable $exception): bool
    {
        if ($this->environment !== 'prod') {
            throw new \RuntimeException($exception->getMessage());
        }

        $this->logger->error($exception->getMessage());

        return false;
    }

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

        // while indexing or not cacheable call?
        if (!$context->getUseCache()) {
            return false;
        }

        if (!$this->client->ping()) {
            return $this->logOrThrowException(new ServerNotAvailableException());
        }

        if ($this->hasIndexDocuments($definition, $context)) {
            return true;
        }

        return $this->logOrThrowException(new NoIndexedDocumentsException($definition->getEntityName()));
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

    public function addTerm(Criteria $criteria, Search $search, Context $context): void
    {
        if (!$criteria->getTerm()) {
            return;
        }

        $bool = new BoolQuery();

        $bool->add(
            new MatchQuery('fullText', $criteria->getTerm(), ['boost' => 2]),
            BoolQuery::SHOULD
        );

        $bool->add(
            new MatchQuery('fullTextBoosted', $criteria->getTerm(), ['boost' => 5]),
            BoolQuery::SHOULD
        );

        $bool->add(
            new MatchQuery('fullText', $criteria->getTerm(), ['fuzziness' => 'auto']),
            BoolQuery::SHOULD
        );

        $bool->add(
            new MatchQuery('description', $criteria->getTerm()),
            BoolQuery::SHOULD
        );

        $bool->addParameter('minimum_should_match', 1);

        $search->addQuery($bool);
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
                /* @var MatchQuery $parsed */
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
        foreach ($this->registry->getDefinitions() as $def) {
            if ($def->getEntityDefinition()->getEntityName() === $definition->getEntityName()) {
                return true;
            }
        }

        return false;
    }

    private function hasIndexDocuments(EntityDefinition $definition, Context $context): bool
    {
        $index = $this->getIndexName($definition, $context->getLanguageId());

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
}
