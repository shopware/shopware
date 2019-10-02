<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Entity\Dbal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder\JoinBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\Entity\Field\SeoUrlAssociationField;

class SeoUrlJoinBuilder implements JoinBuilderInterface
{
    public function join(EntityDefinition $definition, string $joinType, $field, string $on, string $alias, QueryBuilder $queryBuilder, Context $context): void
    {
        if (!$field instanceof SeoUrlAssociationField) {
            throw new \InvalidArgumentException('Expected $field of type' . SeoUrlAssociationField::class);
        }

        $table = $field->getReferenceDefinition()->getEntityName();

        $routeParamKey = 'route_' . Uuid::randomHex();
        $parameters = [
            '#root#' => EntityDefinitionQueryHelper::escape($on),
            '#source_column#' => EntityDefinitionQueryHelper::escape($field->getLocalField()),
            '#alias#' => EntityDefinitionQueryHelper::escape($alias),
            '#reference_column#' => EntityDefinitionQueryHelper::escape($field->getReferenceField()),
        ];

        $queryBuilder->leftJoin(
            EntityDefinitionQueryHelper::escape($on),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                array_keys($parameters),
                array_values($parameters),
                '#alias#.#reference_column# = #root#.#source_column#
                 AND #alias#.route_name = :' . $routeParamKey . '
                 AND #alias#.is_deleted = 0'
            )
        );
        $queryBuilder->setParameter($routeParamKey, $field->getRouteName());
    }
}
