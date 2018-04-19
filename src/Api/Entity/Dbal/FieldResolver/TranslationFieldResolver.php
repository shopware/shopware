<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal\FieldResolver;

use Shopware\Api\Entity\Dbal\EntityDefinitionQueryHelper;
use Shopware\Api\Entity\Dbal\QueryBuilder;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Struct\Uuid;

class TranslationFieldResolver implements FieldResolverInterface
{
    public function resolve(
        string $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        ApplicationContext $context,
        EntityDefinitionQueryHelper $queryHelper,
        bool $raw
    ): void {
        if (!$field instanceof TranslatedField) {
            return;
        }

        $this->joinTranslationTable($root, $definition, $query, $context);

        /** @var string|EntityDefinition $definition */
        if (!$definition::getParentPropertyName() || $raw) {
            return;
        }

        /** @var EntityDefinition $definition */
        $parent = $definition::getFields()->get($definition::getParentPropertyName());
        $alias = $root . '.' . $parent->getPropertyName();

        $this->joinTranslationTable($alias, $definition, $query, $context);
    }

    private function joinTranslationTable(string $root, string $definition, QueryBuilder $query, ApplicationContext $context): void
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
            $versionJoin = ' AND #alias#.`version_id` = #root#.`version_id`';
        }

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($root),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                ['#alias#', '#entity#', '#root#'],
                [
                    EntityDefinitionQueryHelper::escape($alias),
                    $definition::getEntityName(),
                    EntityDefinitionQueryHelper::escape($root),
                ],
                '#alias#.#entity#_id = #root#.id AND #alias#.language_id = :languageId' . $versionJoin .
                ' AND #alias#.#entity#_tenant_id = #root#.tenant_id'
            )
        );

        if (!$context->hasFallback()) {
            return;
        }

        $alias = $root . '.translation.fallback';

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($root),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                ['#alias#', '#entity#', '#root#'],
                [
                    EntityDefinitionQueryHelper::escape($alias),
                    $definition::getEntityName(),
                    EntityDefinitionQueryHelper::escape($root),
                ],
                '#alias#.`#entity#_id` = #root#.`id` AND #alias#.`language_id` = :fallbackLanguageId' . $versionJoin .
                ' AND #alias#.#entity#_tenant_id = #root#.tenant_id'
            )
        );
        $languageId = Uuid::fromStringToBytes($context->getFallbackLanguageId());
        $query->setParameter('fallbackLanguageId', $languageId);
    }
}
