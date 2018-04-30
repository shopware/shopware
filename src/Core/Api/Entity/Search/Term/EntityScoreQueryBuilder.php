<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search\Term;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Search\Query\MatchQuery;
use Shopware\Api\Entity\Search\Query\ScoreQuery;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Write\Flag\SearchRanking;

class EntityScoreQueryBuilder
{
    /**
     * @param SearchPattern $term
     * @param string        $definition
     * @param string        $root
     * @param float         $multiplier
     *
     * @return ScoreQuery[]
     */
    public function buildScoreQueries(SearchPattern $term, string $definition, string $root, float $multiplier = 1): array
    {
        $fields = $this->getQueryFields($definition);

        $queries = [];
        foreach ($fields as $field) {
            $flag = $field->getFlag(SearchRanking::class);

            $ranking = 1 * $multiplier;
            if ($flag) {
                $ranking = $flag->getRanking() * $multiplier;
            }

            /** @var SearchRanking $flag */
            $select = $root . '.' . $field->getPropertyName();

            if ($field instanceof ManyToManyAssociationField) {
                $reference = $field->getReferenceDefinition();

                $queries = array_merge(
                    $queries,
                    $this->buildScoreQueries($term, $reference, $select, $ranking)
                );
                continue;
            }

            if ($field instanceof AssociationInterface) {
                $reference = $field->getReferenceClass();
                $queries = array_merge(
                    $queries,
                    $this->buildScoreQueries($term, $reference, $select, $ranking)
                );
                continue;
            }

            $queries[] = new ScoreQuery(
                new TermQuery($select, $term->getOriginal()->getTerm()),
                $ranking * $term->getOriginal()->getScore()
            );

            $queries[] = new ScoreQuery(
                new MatchQuery($select, $term->getOriginal()->getTerm()),
                $ranking * $term->getOriginal()->getScore() * 0.5
            );

            foreach ($term->getTerms() as $part) {
                $queries[] = new ScoreQuery(
                    new TermQuery($select, $part->getTerm()),
                    $ranking * $part->getScore()
                );

                $queries[] = new ScoreQuery(
                    new MatchQuery($select, $part->getTerm()),
                    $ranking * $part->getScore() * 0.5
                );
            }
        }

        return $queries;
    }

    private function getQueryFields(string $definition): FieldCollection
    {
        /** @var EntityDefinition $definition */
        $fields = $definition::getFields()->filterByFlag(SearchRanking::class);

        if ($fields->count() > 0) {
            return $fields;
        }

        $fields = $definition::getFields()->filterInstance(TranslatedField::class);
        if ($fields->count() > 0) {
            return $fields;
        }

        return $definition::getFields()->filterInstance(StringField::class);
    }
}
