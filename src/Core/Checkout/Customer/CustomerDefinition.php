<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerTag\CustomerTagDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaCustomer\PromotionPersonaCustomerDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Tag\TagDefinition;

class CustomerDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'customer';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomerCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomerEntity::class;
    }

    public function hasManyToManyIdFields(): bool
    {
        return true;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('customer_group_id', 'groupId', CustomerGroupDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('default_payment_method_id', 'defaultPaymentMethodId', PaymentMethodDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('last_payment_method_id', 'lastPaymentMethodId', PaymentMethodDefinition::class))->addFlags(new ApiAware()),
            (new FkField('default_billing_address_id', 'defaultBillingAddressId', CustomerAddressDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('default_shipping_address_id', 'defaultShippingAddressId', CustomerAddressDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new IntField('auto_increment', 'autoIncrement'))->addFlags(new WriteProtected()),
            (new NumberRangeField('customer_number', 'customerNumber', 255))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new FkField('salutation_id', 'salutationId', SalutationDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new StringField('first_name', 'firstName'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('last_name', 'lastName'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('company', 'company'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new PasswordField('password', 'password'))->removeFlag(ApiAware::class),
            (new EmailField('email', 'email'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING, false)),
            (new StringField('title', 'title'))->addFlags(new ApiAware()),
            (new ListField('vat_ids', 'vatIds', StringField::class))->addFlags(new ApiAware()),
            (new StringField('affiliate_code', 'affiliateCode'))->addFlags(new ApiAware()),
            (new StringField('campaign_code', 'campaignCode'))->addFlags(new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            (new BoolField('double_opt_in_registration', 'doubleOptInRegistration'))->addFlags(new ApiAware()),
            (new DateTimeField('double_opt_in_email_sent_date', 'doubleOptInEmailSentDate'))->addFlags(new ApiAware()),
            (new DateTimeField('double_opt_in_confirm_date', 'doubleOptInConfirmDate'))->addFlags(new ApiAware()),
            (new StringField('hash', 'hash'))->addFlags(new ApiAware()),
            (new BoolField('guest', 'guest'))->addFlags(new ApiAware()),
            (new DateTimeField('first_login', 'firstLogin'))->addFlags(new ApiAware()),
            (new DateTimeField('last_login', 'lastLogin'))->addFlags(new ApiAware()),
            (new BoolField('newsletter', 'newsletter'))->addFlags(new ApiAware()),
            (new DateField('birthday', 'birthday'))->addFlags(new ApiAware()),
            (new DateTimeField('last_order_date', 'lastOrderDate'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new IntField('order_count', 'orderCount'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new FloatField('order_total_amount', 'orderTotalAmount'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new CustomFields())->addFlags(new ApiAware()),
            (new StringField('legacy_password', 'legacyPassword'))->removeFlag(ApiAware::class),
            (new StringField('legacy_encoder', 'legacyEncoder'))->removeFlag(ApiAware::class),
            (new ManyToOneAssociationField('group', 'customer_group_id', CustomerGroupDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('defaultPaymentMethod', 'default_payment_method_id', PaymentMethodDefinition::class, 'id', false))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            (new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('lastPaymentMethod', 'last_payment_method_id', PaymentMethodDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('defaultBillingAddress', 'default_billing_address_id', CustomerAddressDefinition::class, 'id', false))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('defaultShippingAddress', 'default_shipping_address_id', CustomerAddressDefinition::class, 'id', false))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('salutation', 'salutation_id', SalutationDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('addresses', CustomerAddressDefinition::class, 'customer_id', 'id'))->addFlags(new ApiAware(), new CascadeDelete()),
            (new OneToManyAssociationField('orderCustomers', OrderCustomerDefinition::class, 'customer_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new ManyToManyAssociationField('tags', TagDefinition::class, CustomerTagDefinition::class, 'customer_id', 'tag_id'))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            new ManyToManyAssociationField('promotions', PromotionDefinition::class, PromotionPersonaCustomerDefinition::class, 'customer_id', 'promotion_id'),
            new OneToManyAssociationField('productReviews', ProductReviewDefinition::class, 'customer_id'),
            new OneToOneAssociationField('recoveryCustomer', 'id', 'customer_id', CustomerRecoveryDefinition::class, false),
            new RemoteAddressField('remote_address', 'remoteAddress'),
            (new ManyToManyIdField('tag_ids', 'tagIds', 'tags'))->addFlags(new ApiAware()),
            new FkField('requested_customer_group_id', 'requestedGroupId', CustomerGroupDefinition::class),
            (new ManyToOneAssociationField('requestedGroup', 'requested_customer_group_id', CustomerGroupDefinition::class, 'id', false)),
            new FkField('bound_sales_channel_id', 'boundSalesChannelId', SalesChannelDefinition::class),
            new ManyToOneAssociationField('boundSalesChannel', 'bound_sales_channel_id', SalesChannelDefinition::class, 'id', false),
            (new OneToManyAssociationField('wishlists', CustomerWishlistDefinition::class, 'customer_id'))->addFlags(new CascadeDelete()),
        ]);

        return $fields;
    }
}
