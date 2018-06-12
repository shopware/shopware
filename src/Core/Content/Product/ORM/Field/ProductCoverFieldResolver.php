<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ORM\Field;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Framework\ORM\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\ORM\Dbal\FieldResolver\FieldResolverInterface;
use Shopware\Core\Framework\ORM\Dbal\QueryBuilder;
use Shopware\Core\Framework\ORM\Field\Field;


class ProductCoverFieldResolver implements FieldResolverInterface
{
    public function resolve(
        string $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
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
            '#reference_column#' => EntityDefinitionQueryHelper::escape($field->getReferenceField()),
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
