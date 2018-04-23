<?php

namespace Shopware\Api\Entity\Field;

use Shopware\Api\Product\Definition\ProductMediaDefinition;

class ProductCoverField extends ManyToOneAssociationField
{
    public function __construct(string $propertyName, bool $loadInBasic)
    {
        parent::__construct($propertyName, 'cover', ProductMediaDefinition::class, $loadInBasic, 'product_id', 'media_join_id');
    }
}