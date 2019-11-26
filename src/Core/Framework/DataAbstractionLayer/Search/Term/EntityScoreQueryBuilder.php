<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;

class EntityScoreQueryBuilder
{
    /**
     * @return ScoreQuery[]
     */
    public function buildScoreQueries(
        SearchPattern $term,
        EntityDefinition $definition,
        string $root,
        Context $context,
        float $multiplier = 1.0
    ): array {
        static $counter = 0;
        ++$counter;

        $fields = $this->getQueryFields($definition, $context);

        $queries = [];
        /** @var Field $field */
        foreach ($fields as $field) {
            /** @var SearchRanking|null $flag */
            $flag = $field->getFlag(SearchRanking::class);

            $ranking = $multiplier;
            if ($flag) {
                $ranking = $flag->getRanking() * $multiplier;
            }

            $select = $root . '.' . $field->getPropertyName();

            if ($field instanceof ManyToManyAssociationField) {
                $queries = array_merge(
                    $queries,
                    $this->buildScoreQueries($term, $field->getToManyReferenceDefinition(), $select, $context, $ranking)
                );

                continue;
            }

            if ($field instanceof AssociationField) {
                $queries = array_merge(
                    $queries,
                    $this->buildScoreQueries($term, $field->getReferenceDefinition(), $select, $context, $ranking)
                );

                continue;
            }

            $queries[] = new ScoreQuery(
                new EqualsFilter($select, $term->getOriginal()->getTerm()),
                $ranking * $term->getOriginal()->getScore()
            );

            $queries[] = new ScoreQuery(
                new ContainsFilter($select, $term->getOriginal()->getTerm()),
                $ranking * $term->getOriginal()->getScore() * 0.5
            );

            foreach ($term->getTerms() as $part) {
                $queries[] = new ScoreQuery(
                    new EqualsFilter($select, $part->getTerm()),
                    $ranking * $part->getScore()
                );

                $queries[] = new ScoreQuery(
                    new ContainsFilter($select, $part->getTerm()),
                    $ranking * $part->getScore() * 0.5
                );
            }
        }

        return $queries;
    }

    private function getQueryFields(EntityDefinition $definition, Context $context): FieldCollection
    {
        /** @var FieldCollection $fields */
        $fields = $definition->getFields()->filterByFlag(SearchRanking::class);

        // exclude read protected fields which are not allowed for the current scope
        $fields = $fields->filter(function (Field $field) use ($context) {
            /** @var ReadProtected|null $flag */
            $flag = $field->getFlag(ReadProtected::class);
            if (!$flag) {
                return true;
            }

            return $flag->isSourceAllowed($context->getScope());
        });

        if ($fields->count() > 0) {
            return $fields;
        }

        $fields = $definition->getFields()->filterInstance(TranslatedField::class);
        if ($fields->count() > 0) {
            return $fields;
        }

        /** @var FieldCollection $field */
        $field = $definition->getFields()->filterInstance(StringField::class);

        return $field;
    }
}
