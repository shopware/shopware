<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class TranslationFieldResolver extends AbstractFieldResolver
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function join(FieldResolverContext $context): string
    {
        $field = $context->getField();
        if (!$field instanceof TranslatedField) {
            return $context->getAlias();
        }

        $definition = $context->getDefinition();
        $translationDefinition = $definition->getTranslationDefinition();
        if (!$translationDefinition) {
            throw new \RuntimeException(\sprintf('Can not detect translation definition of entity %s', $definition->getEntityName()));
        }

        $inherited = $definition->isInheritanceAware() && $context->getContext()->considerInheritance();

        $alias = $context->getAlias() . '.' . $definition->getEntityName() . '_translation';
        $fieldAlias = $alias . '.' . $field->getPropertyName();
        if ($context->getQuery()->hasState($fieldAlias)) {
            return $alias;
        }
        $context->getQuery()->addState($fieldAlias);

        if ($context->getQuery()->hasState($alias)) {
            $this->addTranslationSelects(
                $context->getQuery(),
                $translationDefinition,
                $field,
                $context->getContext(),
                $context->getPath(),
                $alias,
            );

            if ($inherited) {
                $this->addTranslationSelects(
                    $context->getQuery(),
                    $translationDefinition,
                    $field,
                    $context->getContext(),
                    $context->getPath() . '.parent',
                    $context->getAlias() . '.parent.' . $definition->getEntityName() . '_translation',
                );
            }

            return $alias;
        }
        $context->getQuery()->addState($alias);

        $this->addTranslationJoin(
            $context->getQuery(),
            $definition,
            $translationDefinition,
            $field,
            $context->getContext(),
            $context->getPath(),
            $context->getAlias(),
        );

        if ($inherited) {
            $this->addTranslationJoin(
                $context->getQuery(),
                $definition,
                $translationDefinition,
                $field,
                $context->getContext(),
                $context->getPath() . '.parent',
                $context->getAlias() . '.parent',
            );
        }

        return $alias;
    }

    private function addTranslationSelects(
        QueryBuilder $mainQuery,
        EntityDefinition $translationDefinition,
        TranslatedField $field,
        Context $context,
        string $path,
        string $alias,
    ): void {
        $translationQuery = $mainQuery->getTranslationQueryBuilder(EntityDefinitionQueryHelper::escape($alias));
        $translationChain = array_reverse(EntityDefinitionQueryHelper::buildTranslationChain(
            $path,
            $context,
            includeParent: false,
        ));
        foreach ($translationChain as $translationTableAlias) {
            $translationQuery?->addSelect($this->getSelectSQL($translationDefinition, $field, $translationTableAlias));
        }
    }

    private function addTranslationJoin(
        QueryBuilder $query,
        EntityDefinition $definition,
        EntityDefinition $translationDefinition,
        TranslatedField $field,
        Context $context,
        string $path,
        string $alias,
    ): void {
        $rootVersionFieldName = null;
        $translatedVersionFieldName = null;
        if ($definition->isVersionAware()) {
            // field of the translated definition
            $rootVersionFieldName = 'version_id';

            // field of the translationDefinition
            $translatedVersionFieldName = $definition->getEntityName() . '_version_id';
        }

        $translationQuery = $this->getTranslationQuery(
            $definition,
            $this->getSelectSQL($translationDefinition, $field, '#alias#'),
            $path,
            $context,
            $translatedVersionFieldName
        );
        foreach ($translationQuery->getParameters() as $key => $value) {
            $query->setParameter($key, $value);
        }

        $translationAlias = $alias . '.' . $definition->getEntityName() . '_translation';
        $variables = [
            '#alias#' => EntityDefinitionQueryHelper::escape($translationAlias),
            '#foreignKey#' => EntityDefinitionQueryHelper::escape($definition->getEntityName() . '_id'),
            '#on#' => EntityDefinitionQueryHelper::escape($alias),
        ];

        $versionJoin = '';
        if ($rootVersionFieldName && $translatedVersionFieldName) {
            $variables['#rootVersionField#'] = EntityDefinitionQueryHelper::escape($rootVersionFieldName);
            $variables['#translatedVersionField#'] = EntityDefinitionQueryHelper::escape($translatedVersionFieldName);
            $versionJoin = ' AND #alias#.#translatedVersionField# = #on#.#rootVersionField#';
        }

        $query->addTranslationJoin(
            fromAlias: EntityDefinitionQueryHelper::escape($alias),
            joinAlias: EntityDefinitionQueryHelper::escape($translationAlias),
            queryBuilder: $translationQuery,
            joinCondition: str_replace(
                array_keys($variables),
                array_values($variables),
                '#alias#.#foreignKey# = #on#.`id`' . $versionJoin,
            ),
        );
    }

    private function getSelectSQL(
        EntityDefinition $translationDefinition,
        TranslatedField $field,
        string $alias
    ): string {
        $translationField = $translationDefinition->getFields()->get($field->getPropertyName());
        if (!$translationField || !$translationField instanceof StorageAware) {
            throw DataAbstractionLayerException::propertyNotFound(
                $field->getPropertyName(),
                $translationDefinition->getEntityName(),
            );
        }

        return EntityDefinitionQueryHelper::escape($alias) . '.' . EntityDefinitionQueryHelper::escape($translationField->getStorageName()) . ' as ' . EntityDefinitionQueryHelper::escape($alias . '.' . $translationField->getPropertyName());
    }

    private function getTranslationQuery(
        EntityDefinition $definition,
        string $select,
        string $on,
        Context $context,
        ?string $versionFieldName = null,
    ): QueryBuilder {
        $table = $definition->getEntityName() . '_translation';

        $query = new QueryBuilder($this->connection);

        // first language has to be the "from" part, in this case we have to use the system language to enforce we have a record
        $chain = array_reverse($context->getLanguageIdChain());

        $first = array_shift($chain);
        $firstAlias = $on . '.translation';

        $foreignKey = EntityDefinitionQueryHelper::escape($firstAlias) . '.' . EntityDefinitionQueryHelper::escape($definition->getEntityName() . '_id');

        // used as join condition
        $query->addSelect($foreignKey);

        if ($versionFieldName !== null) {
            $versionKey = EntityDefinitionQueryHelper::escape($firstAlias) . '.' . EntityDefinitionQueryHelper::escape($versionFieldName);
            $query->addSelect($versionKey);
        }

        // set first language as from part
        $query->addSelect(str_replace('#alias#', $firstAlias, $select));
        $query->from(EntityDefinitionQueryHelper::escape($table), EntityDefinitionQueryHelper::escape($firstAlias));
        $query->where(EntityDefinitionQueryHelper::escape($firstAlias) . '.`language_id` = :languageId');
        $query->setParameter('languageId', Uuid::fromHexToBytes($first));

        /*
         * Build the following select
         * SELECT ...
         * FROM currency
         * LEFT JOIN (
         *      SELECT
         *          `currency.translation`.currency_id,
         *          `currency.translation`.currency_version_id, (optional)
         *          `currency.translation`.`name` as `currency.translation.name`,
         *          `currency.translation.override_1`.`name` as `currency.translation.override_1.name`,
         *          `currency.translation.override_2`.`name` as `currency.translation.override_2.name`
         *
         *      FROM currency_translation as `currency.translation`
         *
         *      LEFT JOIN currency_translation as `currency.translation.override_1`  (optional)
         *        ON `currency.translation`.currency_id = `currency.translation.override_1`.currency_id
         *        AND `currency.translation.override_1`.language_id = :languageId1 #(parent language)
         *
         *      LEFT JOIN currency_translation as `currency.translation.override_2` (optional)
         *        ON `currency.translation`.currency_id = `currency.translation.override_2`.currency_id
         *        AND `currency.translation.override_2`.language_id = :languageId2 #(current language)
         *
         *      WHERE `currency.translation`.language_id = :languageId #(system language)
         *
         * ) AS `currency.currency_translation`
         *   ON `currency.currency_translation`.currency_id = `currency`.id
         */
        foreach ($chain as $i => $language) {
            ++$i;

            $condition = '#firstAlias#.#column# = #alias#.#column# AND #alias#.`language_id` = :languageId' . $i;

            $alias = $on . '.translation.override_' . $i;

            $variables = [
                '#column#' => EntityDefinitionQueryHelper::escape($definition->getEntityName() . '_id'),
                '#alias#' => EntityDefinitionQueryHelper::escape($alias),
                '#firstAlias#' => EntityDefinitionQueryHelper::escape($firstAlias),
            ];

            if ($versionFieldName !== null) {
                $variables['#versionFieldName#'] = EntityDefinitionQueryHelper::escape($versionFieldName);
                $condition .= ' AND #firstAlias#.#versionFieldName# = #alias#.#versionFieldName#';
            }

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
