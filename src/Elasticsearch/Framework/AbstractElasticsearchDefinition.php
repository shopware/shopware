<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\WildcardQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Elasticsearch\Framework\Indexing\EntityMapper;

abstract class AbstractElasticsearchDefinition
{
    protected EntityMapper $mapper;

    public function __construct(EntityMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    abstract public function getEntityDefinition(): EntityDefinition;

    public function fetch(array $ids, Context $context): array
    {
        return [];
    }

    public function getMapping(Context $context): array
    {
        $definition = $this->getEntityDefinition();

        return [
            '_source' => ['includes' => ['id', 'fullText', 'fullTextBoosted']],
            'properties' => $this->mapper->mapFields($definition, $context),
        ];
    }

    public function buildTermQuery(Context $context, Criteria $criteria): BoolQuery
    {
        $bool = new BoolQuery();

        $term = (string) $criteria->getTerm();

        $queries = [
            new MatchQuery('fullTextBoosted', $term, ['boost' => 10]), // boosted word matches
            new MatchQuery('fullText', $term, ['boost' => 5]), // whole word matches
            new MatchQuery('fullText', $term, ['fuzziness' => 'auto', 'boost' => 3]), // word matches not exactly =>
            new MatchPhrasePrefixQuery('fullText', $term, ['boost' => 1, 'slop' => 5]), // one of the words begins with: "Spachtel" => "Spachtelmasse"
            new WildcardQuery('fullText', '*' . mb_strtolower($term) . '*'), // part of a word matches: "masse" => "Spachtelmasse"
            new MatchQuery('fullText.ngram', $term),
        ];

        foreach ($queries as $query) {
            $bool->add($query, BoolQuery::SHOULD);
        }

        $bool->addParameter('minimum_should_match', 1);

        return $bool;
    }

    protected function stripText(string $text): string
    {
        // Remove all html elements to save up space
        $text = strip_tags($text);

        if (mb_strlen($text) >= 32766) {
            return mb_substr($text, 0, 32766);
        }

        return $text;
    }
}
