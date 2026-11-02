<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
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

        return $this->buildScoreQueriesForPath($term, $definition, $root, $context, $multiplier, []);
    }

    /**
     * @param array<string, true> $path
     *
     * @return ScoreQuery[]
     */
    private function buildScoreQueriesForPath(
        SearchPattern $term,
        EntityDefinition $definition,
        string $root,
        Context $context,
        float $multiplier,
        array $path
    ): array {
        $path[$definition->getEntityName()] = true;

        $fields = $this->getQueryFields($definition, $context);

        $queries = [];
        foreach ($fields as $field) {
            $flag = $field->getFlag(SearchRanking::class);

            $ranking = $multiplier;
            if ($flag) {
                $ranking = $flag->getRanking() * $multiplier;
            }

            if ($field instanceof DateTimeField) {
                if (!$this->validateDateFormat(Defaults::STORAGE_DATE_FORMAT, $term->getOriginal()->getTerm())) {
                    continue;
                }
            }

            $select = $root . '.' . $field->getPropertyName();

            if ($field instanceof AssociationField) {
                $referenceDefinition = $field instanceof ManyToManyAssociationField
                    ? $field->getToManyReferenceDefinition()
                    : $field->getReferenceDefinition();

                if (isset($path[$referenceDefinition->getEntityName()])) {
                    continue;
                }

                $queries = array_merge(
                    $queries,
                    $this->buildScoreQueriesForPath($term, $referenceDefinition, $select, $context, $ranking, $path)
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

            if ($flag && !$flag->tokenize()) {
                continue;
            }

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
        // exclude read protected fields which are not allowed for the current scope
        $isAllowed = static function (Field $field) use ($context) {
            $flag = $field->getFlag(ApiAware::class);
            if ($flag === null) {
                return false;
            }

            return $flag->isSourceAllowed($context->getSource()::class);
        };

        $fields = $definition->getFields()->filterByFlag(SearchRanking::class)->filter($isAllowed);
        if ($fields->count() > 0) {
            return $fields;
        }

        $fields = $definition->getFields()->filterInstance(TranslatedField::class)->filter($isAllowed);
        if ($fields->count() > 0) {
            return $fields;
        }

        return $definition->getFields()->filterInstance(StringField::class)->filter($isAllowed);
    }

    private function validateDateFormat(string $format, string $date): bool
    {
        $dateTime = \DateTime::createFromFormat($format, $date);

        return $dateTime && $dateTime->format($format) === $date;
    }
}
