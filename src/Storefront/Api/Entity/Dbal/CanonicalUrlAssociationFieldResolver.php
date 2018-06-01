<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Entity\Dbal;

use Shopware\Framework\Context;
use Shopware\Framework\ORM\Dbal\EntityDefinitionQueryHelper;
use Shopware\Framework\ORM\Dbal\FieldResolver\FieldResolverInterface;
use Shopware\Framework\ORM\Dbal\QueryBuilder;
use Shopware\Framework\ORM\Field\Field;
use Shopware\Framework\Struct\Uuid;
use Shopware\Storefront\Api\Entity\Field\CanonicalUrlAssociationField;
use Shopware\Storefront\Api\Seo\Definition\SeoUrlDefinition;

class CanonicalUrlAssociationFieldResolver implements FieldResolverInterface
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
        if (!$field instanceof CanonicalUrlAssociationField) {
            return;
        }

        $table = SeoUrlDefinition::getEntityName();
        $alias = $root . '.' . $field->getPropertyName();

        if ($query->hasState($alias)) {
            return;
        }

        $query->addState($alias);

        $key = 'route' . $field->getRouteName();

        $parameters = [
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
                array_keys($parameters),
                array_values($parameters),
                '#alias#.`touchpoint_id` = :touchpointId
                 AND #root#.#source_column# = #alias#.#reference_column# 
                 AND #alias#.name = :' . $key . '
                 AND #alias#.is_canonical = 1'
            )
        );
        $query->setParameter($key, $field->getRouteName());
        $query->setParameter('touchpointId', Uuid::fromStringToBytes($context->getTouchpointId()));
    }
}
