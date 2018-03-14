<?php

namespace Shopware\Api\Entity\Field;

use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Dbal\EntityDefinitionQueryHelper;
use Shopware\Api\Entity\Dbal\QueryBuilder;
use Shopware\Api\Entity\Write\FieldAware\DbalJoinAware;
use Shopware\Api\Entity\Write\Flag\Extension;
use Shopware\Api\Entity\Write\Flag\ReadOnly;
use Shopware\Api\Seo\Definition\SeoUrlDefinition;
use Shopware\Context\Struct\ShopContext;

class CanonicalUrlAssociationField extends ManyToOneAssociationField implements DbalJoinAware
{
    /**
     * @var string
     */
    private $routeName;

    public function __construct(
        string $propertyName,
        string $storageName,
        bool $loadInBasic,
        string $routeName
    ) {
        parent::__construct($propertyName, $storageName, SeoUrlDefinition::class, $loadInBasic, 'foreign_key');
        $this->setFlags(new ReadOnly(), new Extension());
        $this->routeName = $routeName;
    }

    public function join(QueryBuilder $query, string $root, ShopContext $context): void
    {
        $table = SeoUrlDefinition::getEntityName();
        $alias = $root . '.' . $this->getPropertyName();

        $key = 'route' . $this->routeName;

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($root),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                ['#root#', '#source_column#', '#alias#', '#reference_column#'],
                [
                    EntityDefinitionQueryHelper::escape($root),
                    EntityDefinitionQueryHelper::escape($this->getJoinField()),
                    EntityDefinitionQueryHelper::escape($alias),
                    EntityDefinitionQueryHelper::escape($this->getReferenceField()),
                ],
                '#alias#.shop_id = :shopId
                 AND #root#.#source_column# = #alias#.#reference_column# 
                 AND #alias#.name = :' . $key . '
                 AND #alias#.is_canonical = 1'
            )
        );
        $query->setParameter($key, $this->routeName);
        $query->setParameter('shopId', Uuid::fromString($context->getApplicationId())->getBytes());
    }
}