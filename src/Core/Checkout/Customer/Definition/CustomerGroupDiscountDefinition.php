<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Definition;

use Shopware\Checkout\Customer\Collection\CustomerGroupDiscountBasicCollection;
use Shopware\Checkout\Customer\Collection\CustomerGroupDiscountDetailCollection;
use Shopware\Checkout\Customer\Event\CustomerGroupDiscount\CustomerGroupDiscountDeletedEvent;
use Shopware\Checkout\Customer\Event\CustomerGroupDiscount\CustomerGroupDiscountWrittenEvent;
use Shopware\Checkout\Customer\Repository\CustomerGroupDiscountRepository;
use Shopware\Checkout\Customer\Struct\CustomerGroupDiscountBasicStruct;
use Shopware\Checkout\Customer\Struct\CustomerGroupDiscountDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;

class CustomerGroupDiscountDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'customer_group_discount';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('customer_group_id', 'customerGroupId', CustomerGroupDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CustomerGroupDefinition::class))->setFlags(new Required()),
            (new FloatField('percentage_discount', 'percentageDiscount'))->setFlags(new Required()),
            (new FloatField('minimum_cart_amount', 'minimumCartAmount'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('customerGroup', 'customer_group_id', CustomerGroupDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CustomerGroupDiscountRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CustomerGroupDiscountBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CustomerGroupDiscountDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CustomerGroupDiscountWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CustomerGroupDiscountBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return CustomerGroupDiscountDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CustomerGroupDiscountDetailCollection::class;
    }
}
