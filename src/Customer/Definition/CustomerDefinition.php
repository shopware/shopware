<?php declare(strict_types=1);

namespace Shopware\Customer\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Customer\Collection\CustomerBasicCollection;
use Shopware\Customer\Collection\CustomerDetailCollection;
use Shopware\Customer\Event\Customer\CustomerWrittenEvent;
use Shopware\Customer\Repository\CustomerRepository;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\Customer\Struct\CustomerDetailStruct;
use Shopware\Order\Definition\OrderDefinition;
use Shopware\Payment\Definition\PaymentMethodDefinition;
use Shopware\Shop\Definition\ShopDefinition;

class CustomerDefinition extends EntityDefinition
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
        return 'customer';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('customer_group_uuid', 'groupUuid', CustomerGroupDefinition::class))->setFlags(new Required()),
            (new FkField('default_payment_method_uuid', 'defaultPaymentMethodUuid', PaymentMethodDefinition::class))->setFlags(new Required()),
            (new FkField('shop_uuid', 'shopUuid', ShopDefinition::class))->setFlags(new Required()),
            (new FkField('main_shop_uuid', 'mainShopUuid', ShopDefinition::class))->setFlags(new Required()),
            new FkField('last_payment_method_uuid', 'lastPaymentMethodUuid', PaymentMethodDefinition::class),
            new FkField('default_billing_address_uuid', 'defaultBillingAddressUuid', CustomerAddressDefinition::class),
            new FkField('default_shipping_address_uuid', 'defaultShippingAddressUuid', CustomerAddressDefinition::class),
            (new StringField('customer_number', 'number'))->setFlags(new Required()),
            (new StringField('salutation', 'salutation'))->setFlags(new Required()),
            (new StringField('first_name', 'firstName'))->setFlags(new Required()),
            (new StringField('last_name', 'lastName'))->setFlags(new Required()),
            (new StringField('password', 'password'))->setFlags(new Required()),
            (new StringField('email', 'email'))->setFlags(new Required()),
            new StringField('title', 'title'),
            new StringField('encoder', 'encoder'),
            new BoolField('active', 'active'),
            new IntField('account_mode', 'accountMode'),
            new StringField('confirmation_key', 'confirmationKey'),
            new DateField('first_login', 'firstLogin'),
            new DateField('last_login', 'lastLogin'),
            new StringField('session_id', 'sessionId'),
            new BoolField('newsletter', 'newsletter'),
            new StringField('validation', 'validation'),
            new BoolField('affiliate', 'affiliate'),
            new StringField('referer', 'referer'),
            new LongTextField('internal_comment', 'internalComment'),
            new IntField('failed_logins', 'failedLogins'),
            new DateField('locked_until', 'lockedUntil'),
            new DateField('birthday', 'birthday'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('group', 'customer_group_uuid', CustomerGroupDefinition::class, true),
            new ManyToOneAssociationField('defaultPaymentMethod', 'default_payment_method_uuid', PaymentMethodDefinition::class, true),
            new ManyToOneAssociationField('shop', 'shop_uuid', ShopDefinition::class, true),
            new ManyToOneAssociationField('mainShop', 'main_shop_uuid', ShopDefinition::class, false),
            new ManyToOneAssociationField('lastPaymentMethod', 'last_payment_method_uuid', PaymentMethodDefinition::class, true),
            new ManyToOneAssociationField('defaultBillingAddress', 'default_billing_address_uuid', CustomerAddressDefinition::class, true),
            new ManyToOneAssociationField('defaultShippingAddress', 'default_shipping_address_uuid', CustomerAddressDefinition::class, true),
            new OneToManyAssociationField('addresses', CustomerAddressDefinition::class, 'customer_uuid', false, 'uuid'),
            new OneToManyAssociationField('orders', OrderDefinition::class, 'customer_uuid', false, 'uuid'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CustomerRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CustomerBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CustomerWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CustomerBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return CustomerDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CustomerDetailCollection::class;
    }
}
