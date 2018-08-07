<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Entity\Dbal;

use Shopware\Core\Defaults;
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
    public function resolve(string $definition, string $root, Field $field, QueryBuilder $query, Context $context, EntityDefinitionQueryHelper $queryHelper, bool $raw): bool
    {
        if (!$field instanceof CanonicalUrlAssociationField) {
            return false;
        }

        if ($context->getSourceContext()->getOrigin() !== SourceContext::ORIGIN_STOREFRONT_API) {
            $salesChannelId = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL);
        } else {
            $salesChannelId = Uuid::fromHexToBytes($context->getSourceContext()->getSalesChannelId());
        }

        $table = SeoUrlDefinition::getEntityName();
        $alias = $root . '.' . $field->getPropertyName();

        if ($query->hasState($alias)) {
            return true;
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
                '#alias#.`sales_channel_id` = :salesChannelId
                 AND #alias#.#reference_column# = #root#.#source_column#  
                 AND #alias#.name = :' . $key . '
                 AND #alias#.is_canonical = 1
                 AND #alias#.tenant_id = :tenant'
            )
        );
        $query->setParameter($key, $field->getRouteName());
        $query->setParameter('salesChannelId', $salesChannelId);

        return true;
    }
}
