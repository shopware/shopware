<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Definition;

use Shopware\Api\Application\Definition\ApplicationDefinition;
use Shopware\Checkout\Customer\Collection\CustomerBasicCollection;
use Shopware\Checkout\Customer\Collection\CustomerDetailCollection;
use Shopware\Checkout\Customer\Event\Customer\CustomerDeletedEvent;
use Shopware\Checkout\Customer\Event\Customer\CustomerWrittenEvent;
use Shopware\Checkout\Customer\Repository\CustomerRepository;
use Shopware\Checkout\Customer\Struct\CustomerBasicStruct;
use Shopware\Checkout\Customer\Struct\CustomerDetailStruct;
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
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Checkout\Order\Definition\OrderDefinition;
use Shopware\Checkout\Payment\Definition\PaymentMethodDefinition;

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
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('customer_group_id', 'groupId', CustomerGroupDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CustomerGroupDefinition::class))->setFlags(new Required()),

            (new FkField('default_payment_method_id', 'defaultPaymentMethodId', PaymentMethodDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(PaymentMethodDefinition::class, 'default_payment_method_version_id'))->setFlags(new Required()),

            (new FkField('application_id', 'applicationId', ApplicationDefinition::class))->setFlags(new Required()),

            new FkField('last_payment_method_id', 'lastPaymentMethodId', PaymentMethodDefinition::class),
            new ReferenceVersionField(PaymentMethodDefinition::class, 'last_payment_method_version_id'),

            (new FkField('default_billing_address_id', 'defaultBillingAddressId', CustomerAddressDefinition::class))->setFlags(new Required()),
            (new FkField('default_shipping_address_id', 'defaultShippingAddressId', CustomerAddressDefinition::class))->setFlags(new Required()),

            (new StringField('customer_number', 'number'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('salutation', 'salutation'))->setFlags(new Required()),
            (new StringField('first_name', 'firstName'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('last_name', 'lastName'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('password', 'password'))->setFlags(new Required()),
            (new StringField('email', 'email'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
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
            (new LongTextField('internal_comment', 'internalComment'))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            new IntField('failed_logins', 'failedLogins'),
            new DateField('locked_until', 'lockedUntil'),
            new DateField('birthday', 'birthday'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('group', 'customer_group_id', CustomerGroupDefinition::class, true),
            new ManyToOneAssociationField('defaultPaymentMethod', 'default_payment_method_id', PaymentMethodDefinition::class, true),
            new ManyToOneAssociationField('application', 'application_id', ApplicationDefinition::class, true),
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
