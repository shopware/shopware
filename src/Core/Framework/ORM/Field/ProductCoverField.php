<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Field;

use Shopware\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;

class ProductCoverField extends ManyToOneAssociationField
{
    public function __construct(string $propertyName, bool $loadInBasic)
    {
        parent::__construct($propertyName, 'id', ProductMediaDefinition::class, $loadInBasic, 'product_id');
    }
}
