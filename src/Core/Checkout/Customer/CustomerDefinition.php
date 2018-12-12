<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\SearchKeywordAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class CustomerDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'customer';
    }

    public static function useKeywordSearch(): bool
    {
        return true;
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('customer_group_id', 'groupId', CustomerGroupDefinition::class))->setFlags(new Required()),

            (new FkField('default_payment_method_id', 'defaultPaymentMethodId', PaymentMethodDefinition::class))->setFlags(new Required()),

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->setFlags(new Required()),

            new FkField('last_payment_method_id', 'lastPaymentMethodId', PaymentMethodDefinition::class),

            (new FkField('default_billing_address_id', 'defaultBillingAddressId', CustomerAddressDefinition::class))->setFlags(new Required()),
            (new FkField('default_shipping_address_id', 'defaultShippingAddressId', CustomerAddressDefinition::class))->setFlags(new Required()),

            (new IntField('auto_increment', 'autoIncrement'))->setFlags(new ReadOnly()),

            (new StringField('customer_number', 'customerNumber'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new StringField('salutation', 'salutation'),
            (new StringField('first_name', 'firstName'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('last_name', 'lastName'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),

            new PasswordField('password', 'password'),
            (new StringField('email', 'email'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            new StringField('title', 'title'),
            new StringField('encoder', 'encoder'),
            new BoolField('active', 'active'),
            new BoolField('guest', 'guest'),
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
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('group', 'customer_group_id', CustomerGroupDefinition::class, true),
            new ManyToOneAssociationField('defaultPaymentMethod', 'default_payment_method_id', PaymentMethodDefinition::class, true),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, true),
            new ManyToOneAssociationField('lastPaymentMethod', 'last_payment_method_id', PaymentMethodDefinition::class, true),
            (new ManyToOneAssociationField('defaultBillingAddress', 'default_billing_address_id', CustomerAddressDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            new ManyToOneAssociationField('defaultShippingAddress', 'default_shipping_address_id', CustomerAddressDefinition::class, true),
            (new OneToManyAssociationField('addresses', CustomerAddressDefinition::class, 'customer_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('orderCustomers', OrderCustomerDefinition::class, 'customer_id', false, 'id'))->setFlags(new RestrictDelete()),
            new SearchKeywordAssociationField(),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return CustomerCollection::class;
    }

    public static function getStructClass(): string
    {
        return CustomerEntity::class;
    }
}
