<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopware\Core\System\Currency\CurrencyEntity;

/**
 * Test entity for verifying #[Inherited] attribute functionality.
 *
 * @internal
 */
#[Entity('attribute_entity_inheritance', since: '6.7.0.0')]
class AttributeEntityWithInheritance extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Field(type: FieldType::STRING)]
    public string $name;

    /**
     * An inherited string field - should inherit value from parent when null.
     */
    #[Inherited]
    #[Field(type: FieldType::STRING)]
    public ?string $inheritedString = null;

    /**
     * Foreign key to currency - marked as inherited.
     */
    #[Inherited]
    #[ForeignKey(entity: 'currency')]
    public ?string $currencyId = null;

    /**
     * Currency association - marked as inherited.
     */
    #[Inherited]
    #[ManyToOne(entity: 'currency')]
    public ?CurrencyEntity $currency = null;

    /**
     * Test inherited field with custom foreignKey parameter.
     */
    #[Inherited(foreignKey: 'custom_fk')]
    #[Field(type: FieldType::STRING)]
    public ?string $inheritedWithForeignKey = null;

    /**
     * Product association - marked as reverse inherited.
     */
    #[ManyToOne(entity: 'product', onDelete: OnDelete::RESTRICT)]
    #[ReverseInherited(propertyName: 'attributed')]
    public ?ProductEntity $product = null;
}
