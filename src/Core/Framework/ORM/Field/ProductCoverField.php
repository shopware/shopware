<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;

class ProductCoverField extends ManyToOneAssociationField
{
    public function __construct(string $propertyName, bool $loadInBasic)
    {
        parent::__construct($propertyName, 'id', ProductMediaDefinition::class, $loadInBasic, 'product_id');
    }
}
