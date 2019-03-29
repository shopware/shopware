<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Entity\Dbal;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\Entity\Field\CanonicalUrlAssociationField;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlDefinition;

class CanonicalUrlAssociationFieldResolver implements FieldResolverInterface
{
    public function resolve(
        string $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper
    ): bool {
        if (!$field instanceof CanonicalUrlAssociationField) {
            return false;
        }

        $source = $context->getSource();
        if ($source instanceof SalesChannelApiSource) {
            $salesChannelId = Uuid::fromHexToBytes($source->getSalesChannelId());
        } else {
            $salesChannelId = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL);
        }

        $table = SeoUrlDefinition::getEntityName();
        $alias = $root . '.' . $field->getPropertyName();

        if ($query->hasState($alias)) {
            return true;
        }

        $query->addState($alias);

        $routeParamKey = 'route_' . Uuid::randomHex();
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
                 AND #alias#.route_name = :' . $routeParamKey . '
                 AND #alias#.is_canonical = 1
                 AND #alias#.is_valid = 1'
            )
        );
        $query->setParameter($routeParamKey, $field->getRouteName());
        $query->setParameter('salesChannelId', $salesChannelId);

        return true;
    }
}
