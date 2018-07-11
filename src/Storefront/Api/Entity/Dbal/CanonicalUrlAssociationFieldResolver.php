<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Entity\Dbal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\ORM\Dbal\FieldResolver\FieldResolverInterface;
use Shopware\Core\Framework\ORM\Dbal\QueryBuilder;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Storefront\Api\Entity\Field\CanonicalUrlAssociationField;
use Shopware\Storefront\Api\Seo\SeoUrlDefinition;

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

        if ($context->getSourceContext()->getOrigin() !== SourceContext::ORIGIN_STOREFRONT_API) {
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
        $query->setParameter('touchpointId', Uuid::fromHexToBytes($context->getSourceContext()->getTouchpointId()));
    }
}
