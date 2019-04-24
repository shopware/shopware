<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerTag\CustomerTagDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaCustomer\PromotionPersonaCustomerDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Language\LanguageDefinition;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Tag\TagDefinition;

class CustomerDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'customer';
    }

    public static function getCollectionClass(): string
    {
        return CustomerCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CustomerEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('customer_group_id', 'groupId', CustomerGroupDefinition::class))->addFlags(new Required()),

            (new FkField('default_payment_method_id', 'defaultPaymentMethodId', PaymentMethodDefinition::class))->addFlags(new Required()),

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),

            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required()),

            new FkField('last_payment_method_id', 'lastPaymentMethodId', PaymentMethodDefinition::class),

            (new FkField('default_billing_address_id', 'defaultBillingAddressId', CustomerAddressDefinition::class))->addFlags(new Required()),
            (new FkField('default_shipping_address_id', 'defaultShippingAddressId', CustomerAddressDefinition::class))->addFlags(new Required()),

            (new IntField('auto_increment', 'autoIncrement'))->addFlags(new WriteProtected()),

            (new NumberRangeField('customer_number', 'customerNumber'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new FkField('salutation_id', 'salutationId', SalutationDefinition::class))->addFlags(new Required()),
            (new StringField('first_name', 'firstName'))->addFlags(new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('last_name', 'lastName'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('company', 'company'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),

            new PasswordField('password', 'password'),
            (new StringField('email', 'email'))->addFlags(new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            new StringField('title', 'title'),
            new BoolField('active', 'active'),
            new BoolField('guest', 'guest'),
            new DateField('first_login', 'firstLogin'),
            new DateField('last_login', 'lastLogin'),
            new BoolField('newsletter', 'newsletter'),
            new DateField('birthday', 'birthday'),
            new AttributesField(),
            new CreatedAtField(),
            new UpdatedAtField(),
            new StringField('legacy_password', 'legacyPassword'),
            new StringField('legacy_encoder', 'legacyEncoder'),
            new ManyToOneAssociationField('group', 'customer_group_id', CustomerGroupDefinition::class, 'id', true),
            new ManyToOneAssociationField('defaultPaymentMethod', 'default_payment_method_id', PaymentMethodDefinition::class, 'id', true),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', true),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
            new ManyToOneAssociationField('lastPaymentMethod', 'last_payment_method_id', PaymentMethodDefinition::class, 'id', true),
            (new ManyToOneAssociationField('defaultBillingAddress', 'default_billing_address_id', CustomerAddressDefinition::class, 'id', true))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            new ManyToOneAssociationField('defaultShippingAddress', 'default_shipping_address_id', CustomerAddressDefinition::class, 'id', true),
            new ManyToOneAssociationField('salutation', 'salutation_id', SalutationDefinition::class, 'id', true),
            (new OneToManyAssociationField('addresses', CustomerAddressDefinition::class, 'customer_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('orderCustomers', OrderCustomerDefinition::class, 'customer_id', 'id'))->addFlags(new RestrictDelete()),
            new ManyToManyAssociationField('tags', TagDefinition::class, CustomerTagDefinition::class, 'customer_id', 'tag_id'),

            new ManyToManyAssociationField('promotions', PromotionDefinition::class, PromotionPersonaCustomerDefinition::class, 'customer_id', 'promotion_id'),
        ]);
    }
}
