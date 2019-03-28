<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Uuid\Uuid;

class TranslationFieldResolver implements FieldResolverInterface
{
    public function resolve(
        string $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper,
        bool $considerInheritance
    ): bool {
        if (!$field instanceof TranslatedField) {
            return false;
        }

        $chain = EntityDefinitionQueryHelper::buildTranslationChain($root, $context, $definition::isInheritanceAware() && $considerInheritance);
        foreach ($chain as $part) {
            $this->joinTranslationTable($part, $definition, $query);
        }

        return true;
    }

    private function joinTranslationTable(array $part, string $definition, QueryBuilder $query): void
    {
        $table = $definition::getEntityName() . '_translation';
        $parameterName = str_replace('.', '_', $part['name']) . 'LanguageId';

        if ($query->hasState($part['alias'])) {
            return;
        }
        $query->addState($part['alias']);

        $versionJoin = '';
        if ($definition::isVersionAware()) {
            $versionJoin = ' AND #alias#.`#entity#_version_id` = #root#.`version_id`';
        }

        $parameters = [
            '#alias#' => EntityDefinitionQueryHelper::escape($part['alias']),
            '#entity#' => $definition::getEntityName(),
            '#root#' => EntityDefinitionQueryHelper::escape($part['root']),
        ];

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

        $languageId = Uuid::fromHexToBytes($part['id']);
        $query->setParameter($parameterName, $languageId);
    }
}
