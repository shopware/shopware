<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal\FieldResolver;

use Shopware\Api\Entity\Dbal\EntityDefinitionQueryHelper;
use Shopware\Api\Entity\Dbal\QueryBuilder;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\ProductCoverField;
use Shopware\Api\Product\Definition\ProductMediaDefinition;
use Shopware\Context\Struct\ApplicationContext;

class ProductCoverFieldResolver implements FieldResolverInterface
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
        if (!$field instanceof ProductCoverField) {
            return;
        }

        $table = ProductMediaDefinition::getEntityName();
        $alias = $root . '.' . $field->getPropertyName();

        $query->addState($alias);

        $mapping = [
            '#root#' => EntityDefinitionQueryHelper::escape($root),
            '#source_column#' => EntityDefinitionQueryHelper::escape($field->getStorageName()),
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => EntityDefinitionQueryHelper::escape($field->getReferenceField())
        ];

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($root),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($mapping),
                array_values($mapping),
                '#root#.#source_column# = #alias#.#reference_column# 
                 AND #alias#.is_cover = 1'
            )
        );
    }
}
