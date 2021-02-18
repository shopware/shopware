<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\WildcardQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
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

    /**
     * This function defines which data should be selected and provided to the elasticsearch server
     *
     * @feature-deprecated (flag:FEATURE_NEXT_12158) tag:v6.4.0 - The fetch method will be used
     */
    public function extendCriteria(Criteria $criteria): void
    {
    }

    /**
     * Allows to add none database specific data to the entities.
     * This function is typically used to build elasticsearch completion fields,
     * n-grams or other calculated fields for the search engine
     *
     * @feature-deprecated (flag:FEATURE_NEXT_12158) tag:v6.4.0 - The fetch method will be used
     */
    public function extendEntities(EntityCollection $collection): EntityCollection
    {
        return $collection;
    }

    public function extendDocuments(EntityCollection $collection, array $documents, Context $context): array
    {
        return $documents;
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

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_12158) tag:v6.4.0 - Use extend entities instead
     */
    public function buildFullText(Entity $entity): FullText
    {
        $fullText = [];
        $boosted = [];

        foreach ($this->getEntityDefinition()->getFields() as $field) {
            $real = $field;

            $isTranslated = $field instanceof TranslatedField;

            if ($isTranslated) {
                $real = EntityDefinitionQueryHelper::getTranslatedField($this->getEntityDefinition(), $field);
            }

            if (!$real instanceof StringField) {
                continue;
            }

            try {
                if ($isTranslated) {
                    $value = $entity->getTranslation($real->getPropertyName());
                } else {
                    $value = $entity->get($real->getPropertyName());
                }
            } catch (\Exception $e) {
                continue;
            }

            $fullText[] = $value;

            if ($isTranslated || $field instanceof NumberRangeField) {
                $boosted[] = $value;
            }
        }

        $fullText = array_filter($fullText);
        $boosted = array_filter($boosted);

        return new FullText(implode(' ', $fullText), implode(' ', $boosted));
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
