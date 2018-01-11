<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Definition;

use Shopware\Api\Customer\Collection\CustomerBasicCollection;
use Shopware\Api\Customer\Collection\CustomerDetailCollection;
use Shopware\Api\Customer\Event\Customer\CustomerDeletedEvent;
use Shopware\Api\Customer\Event\Customer\CustomerWrittenEvent;
use Shopware\Api\Customer\Repository\CustomerRepository;
use Shopware\Api\Customer\Struct\CustomerBasicStruct;
use Shopware\Api\Customer\Struct\CustomerDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Order\Definition\OrderDefinition;
use Shopware\Api\Payment\Definition\PaymentMethodDefinition;
use Shopware\Api\Shop\Definition\ShopDefinition;

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
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('customer_group_id', 'groupId', CustomerGroupDefinition::class))->setFlags(new Required()),
            (new FkField('default_payment_method_id', 'defaultPaymentMethodId', PaymentMethodDefinition::class))->setFlags(new Required()),
            (new FkField('shop_id', 'shopId', ShopDefinition::class))->setFlags(new Required()),
            (new FkField('main_shop_id', 'mainShopId', ShopDefinition::class))->setFlags(new Required()),
            new FkField('last_payment_method_id', 'lastPaymentMethodId', PaymentMethodDefinition::class),
            (new FkField('default_billing_address_id', 'defaultBillingAddressId', CustomerAddressDefinition::class))->setFlags(new Required()),
            (new FkField('default_shipping_address_id', 'defaultShippingAddressId', CustomerAddressDefinition::class))->setFlags(new Required()),
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
            new ManyToOneAssociationField('group', 'customer_group_id', CustomerGroupDefinition::class, true),
            new ManyToOneAssociationField('defaultPaymentMethod', 'default_payment_method_id', PaymentMethodDefinition::class, true),
            new ManyToOneAssociationField('shop', 'shop_id', ShopDefinition::class, true),
            new ManyToOneAssociationField('mainShop', 'main_shop_id', ShopDefinition::class, false),
            new ManyToOneAssociationField('lastPaymentMethod', 'last_payment_method_id', PaymentMethodDefinition::class, true),
            new ManyToOneAssociationField('defaultBillingAddress', 'default_billing_address_id', CustomerAddressDefinition::class, true),
            new ManyToOneAssociationField('defaultShippingAddress', 'default_shipping_address_id', CustomerAddressDefinition::class, true),
            (new OneToManyAssociationField('addresses', CustomerAddressDefinition::class, 'customer_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'customer_id', false, 'id'))->setFlags(new RestrictDelete()),
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

    public static function getDeletedEventClass(): string
    {
        return CustomerDeletedEvent::class;
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
