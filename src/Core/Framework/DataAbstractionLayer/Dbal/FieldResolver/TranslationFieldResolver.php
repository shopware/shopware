<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder\JoinBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class TranslationFieldResolver extends AbstractFieldResolver implements FieldResolverInterface
{
    /**
     * @deprecated tag:v6.4.0 - Will be removed
     *
     * @var JoinBuilderInterface
     */
    private $joinBuilder;

    // @deprecated tag:v6.4.0 - Will be removed
    public function __construct(JoinBuilderInterface $joinBuilder)
    {
        $this->joinBuilder = $joinBuilder;
    }

    /**
     * @deprecated tag:v6.4.0 - Will be removed
     */
    public function getJoinBuilder(): JoinBuilderInterface
    {
        return $this->joinBuilder;
    }

    public function join(FieldResolverContext $context): string
    {
        $field = $context->getField();
        if (!$field instanceof TranslatedField) {
            return $context->getAlias();
        }

        $alias = $context->getAlias() . '.' . $context->getDefinition()->getEntityName() . '_translation';
        if ($context->getQuery()->hasState($alias)) {
            return $alias;
        }
        $context->getQuery()->addState($alias);

        $query = $this->getTranslationQuery($context->getDefinition(), $context->getPath(), $context->getQuery(), $context->getContext());
        $variables = [
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#foreignKey#' => EntityDefinitionQueryHelper::escape($context->getDefinition()->getEntityName() . '_id'),
            '#on#' => EntityDefinitionQueryHelper::escape($context->getAlias()),
        ];

        $inherited = $context->getDefinition()->isInheritanceAware() && $context->getContext()->considerInheritance();

        $context->getQuery()->leftJoin(
            EntityDefinitionQueryHelper::escape($context->getAlias()),
            '(' . $query->getSQL() . ')',
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($variables),
                array_values($variables),
                '#alias#.#foreignKey# = #on#.`id`'
            )
        );

        foreach ($query->getParameters() as $key => $value) {
            $context->getQuery()->setParameter($key, $value);
        }

        if (!$inherited) {
            return $alias;
        }

        $query = $this->getTranslationQuery($context->getDefinition(), $context->getPath() . '.parent', $context->getQuery(), $context->getContext());

        $variables = [
            '#alias#' => EntityDefinitionQueryHelper::escape($alias . '.parent'),
            '#foreignKey#' => EntityDefinitionQueryHelper::escape($context->getDefinition()->getEntityName() . '_id'),
            '#on#' => EntityDefinitionQueryHelper::escape($context->getAlias() . '.parent'),
        ];

        $context->getQuery()->leftJoin(
            EntityDefinitionQueryHelper::escape($context->getAlias()),
            '(' . $query->getSQL() . ')',
            EntityDefinitionQueryHelper::escape($alias . '.parent'),
            str_replace(
                array_keys($variables),
                array_values($variables),
                '#alias#.#foreignKey# = #on#.`id`'
            )
        );

        return $alias;
    }

    /**
     * @deprecated tag:v6.4.0 - Will be removed
     */
    public function resolve(
        EntityDefinition $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper
    ): bool {
        if (!$field instanceof TranslatedField) {
            return false;
        }

        $alias = $root . '.' . $definition->getEntityName() . '_translation';
        if ($query->hasState($alias)) {
            return false;
        }
        $query->addState($alias);

        $this->getJoinBuilder()->join($definition, JoinBuilderInterface::LEFT_JOIN, $field, $root, $alias, $query, $context);

        return true;
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

    private function getTranslationQuery(EntityDefinition $definition, string $on, QueryBuilder $queryBuilder, Context $context): QueryBuilder
    {
        $table = $definition->getEntityName() . '_translation';

        $query = new QueryBuilder($queryBuilder->getConnection());

        $translation = $definition->getTranslationDefinition();
        if (!$translation) {
            throw new \RuntimeException(sprintf('Can not detect translation definition of entity %s', $definition->getEntityName()));
        }

        $select = $this->getSelectTemplate($translation);

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
