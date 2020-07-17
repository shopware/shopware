<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Uuid\Uuid;

class TranslatedJoinBuilder implements JoinBuilderInterface
{
    public function join(EntityDefinition $definition, string $joinType, $field, string $on, string $root, QueryBuilder $queryBuilder, Context $context): void
    {
        if (!$field instanceof TranslatedField) {
            throw new \InvalidArgumentException('Expected ' . TranslatedField::class);
        }

        $query = $this->getTranslationQuery($definition, $on, $queryBuilder, $context);

        $variables = [
            '#alias#' => EntityDefinitionQueryHelper::escape($root),
            '#foreignKey#' => EntityDefinitionQueryHelper::escape($definition->getEntityName() . '_id'),
            '#on#' => EntityDefinitionQueryHelper::escape($on),
        ];

        $queryBuilder->leftJoin(
            EntityDefinitionQueryHelper::escape($on),
            '(' . $query->getSQL() . ')',
            EntityDefinitionQueryHelper::escape($root),
            str_replace(
                array_keys($variables),
                array_values($variables),
                '#alias#.#foreignKey# = #on#.`id`'
            )
        );

        foreach ($query->getParameters() as $key => $value) {
            $queryBuilder->setParameter($key, $value);
        }

        if (!$definition->isInheritanceAware() || !$context->considerInheritance()) {
            return;
        }

        $query = $this->getTranslationQuery($definition, $on . '.parent', $queryBuilder, $context);

        $variables = [
            '#alias#' => EntityDefinitionQueryHelper::escape($root . '.parent'),
            '#foreignKey#' => EntityDefinitionQueryHelper::escape($definition->getEntityName() . '_id'),
            '#on#' => EntityDefinitionQueryHelper::escape($on . '.parent'),
        ];

        $queryBuilder->leftJoin(
            EntityDefinitionQueryHelper::escape($on),
            '(' . $query->getSQL() . ')',
            EntityDefinitionQueryHelper::escape($root . '.parent'),
            str_replace(
                array_keys($variables),
                array_values($variables),
                '#alias#.#foreignKey# = #on#.`id`'
            )
        );
    }

    private function getSelectTemplate(EntityDefinition $definition)
    {
        $select = $definition->getFields()->fmap(function (Field $field) {
            if (!$field instanceof StorageAware) {
                return null;
            }

            return '`#alias#`.' . $field->getStorageName() . ' as `#alias#.' . $field->getPropertyName() . '`';
        });

        return implode(', ', $select);
    }

    private function getTranslationQuery(EntityDefinition $definition, string $on, QueryBuilder $queryBuilder, Context $context): QueryBuilder
    {
        $table = $definition->getEntityName() . '_translation';

        $query = new QueryBuilder($queryBuilder->getConnection());

        $select = $this->getSelectTemplate($definition->getTranslationDefinition());

        // first language has to be the from part, in this case we have to use the system language to enforce we have a record
        $chain = array_reverse($context->getLanguageIdChain());

        $first = array_shift($chain);
        $firstAlias = $on . '.translation';

        $foreignKey = EntityDefinitionQueryHelper::escape($firstAlias) . '.' . $definition->getEntityName() . '_id';

        // used as join condition
        $query->addSelect($foreignKey);

        // set first language as from part
        $query->addSelect(str_replace('#alias#', $firstAlias, $select));
        $query->from(EntityDefinitionQueryHelper::escape($table), EntityDefinitionQueryHelper::escape($firstAlias));
        $query->where(EntityDefinitionQueryHelper::escape($firstAlias) . '.language_id = :languageId');
        $query->setParameter('languageId', Uuid::fromHexToBytes($first));

        /*
         * Build the following select
         * SELECT ...
         * FROM currency
         * LEFT JOIN (
         *      SELECT
         *          `currency.translation`.currency_id,
         *          `currency.translation`.`name` as `currency.translation.name`
         *          `currency.translation.fallback_1`.`name` as `currency.translation.fallback_1.name`
         *          `currency.translation.fallback_2`.`name` as `currency.translation.fallback_2.name`
         *
         *      FROM currency_translation as `currency.translation`
         *
         *      LEFT JOIN currency_translation as `currency.translation.fallback_1`  (optional)
         *        ON `currency.translation`.currency_id = `currency.translation.fallback_1`.currency_id
         *        AND `currency.translation.fallback_1`.language_id = :languageId1 #(parent language)
         *
         *      LEFT JOIN currency_translation as `currency.translation.fallback_2` (optional)
         *        ON `currency.translation`.currency_id = `currency.translation.fallback_2`.currency_id
         *        AND `currency.translation.fallback_2`.language_id = :languageId2 #(current language)
         *
         *      WHERE `currency.translation`.language_id = :languageId #(system language)
         *
         * ) AS `currency.currency_translation`
         *   ON `currency.currency_translation`.currency_id = `currency`.id
         */
        foreach ($chain as $i => $language) {
            ++$i;

            $condition = '#firstAlias#.#column# = #alias#.#column# AND #alias#.language_id = :languageId' . $i;

            $alias = $on . '.translation.fallback_' . $i;

            $variables = [
                '#column#' => EntityDefinitionQueryHelper::escape($definition->getEntityName() . '_id'),
                '#alias#' => EntityDefinitionQueryHelper::escape($alias),
                '#firstAlias#' => EntityDefinitionQueryHelper::escape($firstAlias),
            ];

            $query->leftJoin(
                EntityDefinitionQueryHelper::escape($firstAlias),
                EntityDefinitionQueryHelper::escape($table),
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(array_keys($variables), array_values($variables), $condition)
            );

            $query->addSelect(str_replace('#alias#', $alias, $select));
            $query->setParameter('languageId' . $i, Uuid::fromHexToBytes($language));
        }

        return $query;
    }
}
