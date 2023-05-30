<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
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
            throw new \RuntimeException(sprintf('Can not detect translation definition of entity %s', $definition->getEntityName()));
        }

        $alias = $context->getAlias() . '.' . $definition->getEntityName() . '_translation';
        if ($context->getQuery()->hasState($alias)) {
            return $alias;
        }
        $context->getQuery()->addState($alias);

        $variables = [
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#foreignKey#' => EntityDefinitionQueryHelper::escape($definition->getEntityName() . '_id'),
            '#on#' => EntityDefinitionQueryHelper::escape($context->getAlias()),
        ];

        $rootVersionFieldName = null;
        $translatedVersionFieldName = null;
        $versionJoin = '';

        if ($definition->isVersionAware()) {
            // field of the translated definition
            $rootVersionFieldName = 'version_id';

            // field of the translationDefinition
            $translatedVersionFieldName = $definition->getEntityName() . '_version_id';
        }

        $query = $this->getTranslationQuery($definition, $translationDefinition, $context->getPath(), $context->getContext(), $translatedVersionFieldName);

        if ($rootVersionFieldName && $translatedVersionFieldName) {
            $variables['#rootVersionField#'] = $rootVersionFieldName;
            $variables['#translatedVersionField#'] = $translatedVersionFieldName;
            $versionJoin = ' AND #alias#.#translatedVersionField# = #on#.#rootVersionField#';
        }

        $context->getQuery()->leftJoin(
            EntityDefinitionQueryHelper::escape($context->getAlias()),
            '(' . $query->getSQL() . ')',
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($variables),
                array_values($variables),
                '#alias#.#foreignKey# = #on#.`id`' . $versionJoin
            )
        );

        foreach ($query->getParameters() as $key => $value) {
            $context->getQuery()->setParameter($key, $value);
        }

        $inherited = $definition->isInheritanceAware() && $context->getContext()->considerInheritance();
        if (!$inherited) {
            return $alias;
        }

        $query = $this->getTranslationQuery($definition, $translationDefinition, $context->getPath() . '.parent', $context->getContext(), $translatedVersionFieldName);

        $variables = [
            '#alias#' => EntityDefinitionQueryHelper::escape($alias . '.parent'),
            '#foreignKey#' => EntityDefinitionQueryHelper::escape($definition->getEntityName() . '_id'),
            '#on#' => EntityDefinitionQueryHelper::escape($context->getAlias() . '.parent'),
        ];

        if ($rootVersionFieldName && $translatedVersionFieldName) {
            $variables['#rootVersionField#'] = $rootVersionFieldName;
            $variables['#translatedVersionField#'] = $translatedVersionFieldName;
            $versionJoin = ' AND #alias#.#translatedVersionField# = #on#.#rootVersionField#';
        }

        $context->getQuery()->leftJoin(
            EntityDefinitionQueryHelper::escape($context->getAlias()),
            '(' . $query->getSQL() . ')',
            EntityDefinitionQueryHelper::escape($alias . '.parent'),
            str_replace(
                array_keys($variables),
                array_values($variables),
                '#alias#.#foreignKey# = #on#.`id`' . $versionJoin
            )
        );

        return $alias;
    }

    private function getSelectTemplate(EntityDefinition $definition): string
    {
        $select = $definition->getFields()->fmap(function (Field $field) {
            if (!$field instanceof StorageAware) {
                return null;
            }

            return '`#alias#`.' . $field->getStorageName() . ' as `#alias#.' . $field->getPropertyName() . '`';
        });

        return implode(', ', $select);
    }

    private function getTranslationQuery(
        EntityDefinition $definition,
        EntityDefinition $translationDefinition,
        string $on,
        Context $context,
        ?string $versionFieldName = null,
    ): QueryBuilder {
        $table = $definition->getEntityName() . '_translation';

        $query = new QueryBuilder($this->connection);

        $select = $this->getSelectTemplate($translationDefinition);

        // first language has to be the "from" part, in this case we have to use the system language to enforce we have a record
        $chain = array_reverse($context->getLanguageIdChain());

        $first = array_shift($chain);
        $firstAlias = $on . '.translation';

        $foreignKey = EntityDefinitionQueryHelper::escape($firstAlias) . '.' . $definition->getEntityName() . '_id';

        // used as join condition
        $query->addSelect($foreignKey);

        if ($versionFieldName !== null) {
            $versionKey = EntityDefinitionQueryHelper::escape($firstAlias) . '.' . $versionFieldName;
            $query->addSelect($versionKey);
        }

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
         *          `currency.translation`.currency_version_id, (optional)
         *          `currency.translation`.`name` as `currency.translation.name`,
         *          `currency.translation.fallback_1`.`name` as `currency.translation.fallback_1.name`,
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

            if ($versionFieldName !== null) {
                $variables['#versionFieldName#'] = $versionFieldName;
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
