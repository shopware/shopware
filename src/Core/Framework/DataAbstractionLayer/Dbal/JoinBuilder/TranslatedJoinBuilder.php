<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Uuid\Uuid;

class TranslatedJoinBuilder implements JoinBuilderInterface
{
    public function join(EntityDefinition $definition, string $joinType, $field, string $on, string $alias, QueryBuilder $queryBuilder, Context $context): void
    {
        if (!$field instanceof TranslatedField) {
            throw new \InvalidArgumentException('Expected ' . TranslatedField::class);
        }

        $chain = EntityDefinitionQueryHelper::buildTranslationChain($on, $context, $definition->isInheritanceAware() && $context->considerInheritance());

        foreach ($chain as $part) {
            $this->joinTranslationTable($joinType, $part, $definition, $queryBuilder);
        }
    }

    private function joinTranslationTable(string $joinType, array $part, EntityDefinition $definition, QueryBuilder $query): void
    {
        $table = $definition->getEntityName() . '_translation';
        $parameterName = str_replace('.', '_', $part['name']) . 'LanguageId';

        $versionJoin = '';
        if ($definition->isVersionAware()) {
            $versionJoin = ' AND #alias#.`#entity#_version_id` = #root#.`version_id`';
        }

        $parameters = [
            '#alias#' => EntityDefinitionQueryHelper::escape($part['alias']),
            '#entity#' => $definition->getEntityName(),
            '#root#' => EntityDefinitionQueryHelper::escape($part['root']),
        ];

        if ($joinType === JoinBuilderInterface::INNER_JOIN) {
            // does not make much sense. we should bundle it as a sub-select join.
            $query->innerJoin(
                EntityDefinitionQueryHelper::escape($part['root']),
                EntityDefinitionQueryHelper::escape($table),
                EntityDefinitionQueryHelper::escape($part['alias']),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#alias#.`#entity#_id` = #root#.`id` AND #alias#.`language_id` = :' . $parameterName . $versionJoin
                )
            );
        } else {
            $query->leftJoin(
                EntityDefinitionQueryHelper::escape($part['root']),
                EntityDefinitionQueryHelper::escape($table),
                EntityDefinitionQueryHelper::escape($part['alias']),
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    '#alias#.`#entity#_id` = #root#.`id` AND #alias#.`language_id` = :' . $parameterName . $versionJoin
                )
            );
        }

        $languageId = Uuid::fromHexToBytes($part['id']);
        $query->setParameter($parameterName, $languageId);
    }
}
