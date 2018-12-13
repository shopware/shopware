<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Struct\Uuid;

class TranslationFieldResolver implements FieldResolverInterface
{
    public function resolve(
        string $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper
    ): bool {
        if (!$field instanceof TranslatedField) {
            return false;
        }

        $this->joinTranslationTable($root, $definition, $query, $context);

        /** @var string|EntityDefinition $definition */
        if (!$definition::isInheritanceAware()) {
            return true;
        }

        /** @var EntityDefinition $definition */
        $alias = $root . '.parent';

        $this->joinTranslationTable($alias, $definition, $query, $context);

        return true;
    }

    private function joinTranslationTable(string $root, string $definition, QueryBuilder $query, Context $context): void
    {
        $alias = $root . '.translation';
        if ($query->hasState($alias)) {
            return;
        }

        $query->addState($alias);

        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName() . '_translation';

        $languageId = Uuid::fromStringToBytes($context->getLanguageId());
        $query->setParameter('languageId', $languageId);

        $versionJoin = '';
        if ($definition::isVersionAware()) {
            $versionJoin = ' AND #alias#.`#entity#_version_id` = #root#.`version_id`';
        }

        $parameters = [
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#entity#' => $definition::getEntityName(),
            '#root#' => EntityDefinitionQueryHelper::escape($root),
        ];

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($root),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#alias#.`#entity#_id` = #root#.`id` AND #alias#.`language_id` = :languageId' . $versionJoin
            )
        );

        if (!$context->hasFallback()) {
            return;
        }

        $alias = $root . '.translation.fallback';

        $parameters = [
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#entity#' => $definition::getEntityName(),
            '#root#' => EntityDefinitionQueryHelper::escape($root),
        ];

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($root),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#alias#.`#entity#_id` = #root#.`id` AND #alias#.`language_id` = :fallbackLanguageId' . $versionJoin
            )
        );
        $languageId = Uuid::fromStringToBytes($context->getFallbackLanguageId());
        $query->setParameter('fallbackLanguageId', $languageId);
    }
}
