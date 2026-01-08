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
use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\IgnoreInOpenapiSchema;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\NoConstraint;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\System\User\UserDefinition;

#[Package('checkout')]
class CustomerDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'customer';

    public const MAX_LENGTH_FIRST_NAME = 255;
    public const MAX_LENGTH_LAST_NAME = 255;
    public const MAX_LENGTH_TITLE = 100;

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

    public function getDefaults(): array
    {
        return [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
        ];
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
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the customer.'),
            (new FkField('customer_group_id', 'groupId', CustomerGroupDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of customer group.'),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of sales channel.'),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of language.'),
            (new FkField('last_payment_method_id', 'lastPaymentMethodId', PaymentMethodDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of previous payment method.'),
            (new FkField('default_billing_address_id', 'defaultBillingAddressId', CustomerAddressDefinition::class))->addFlags(new ApiAware(), new Required(), new NoConstraint())->setDescription('Unique identity of default billing address.'),
            (new FkField('default_shipping_address_id', 'defaultShippingAddressId', CustomerAddressDefinition::class))->addFlags(new ApiAware(), new Required(), new NoConstraint())->setDescription('Unique identity of default shipping address.'),
            new AutoIncrementField(),
            (new NumberRangeField('customer_number', 'customerNumber', 255))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Unique  number assigned to identity a customer.'),
            (new FkField('salutation_id', 'salutationId', SalutationDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of salutation.'),
            (new StringField('first_name', 'firstName', self::MAX_LENGTH_FIRST_NAME))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('First name of the customer.'),
            (new StringField('last_name', 'lastName', self::MAX_LENGTH_LAST_NAME))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Last name of the customer.'),
            (new StringField('company', 'company'))->addFlags(new ApiAware(), new IgnoreInOpenapiSchema(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Company name of the customer.'),
            (new PasswordField('password', 'password', \PASSWORD_DEFAULT, [], PasswordField::FOR_CUSTOMER))->removeFlag(ApiAware::class),
            (new EmailField('email', 'email'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING, false))->setDescription('Email ID of the customer.'),
            (new StringField('title', 'title', self::MAX_LENGTH_TITLE))->addFlags(new ApiAware())->setDescription('Titles or honorifics like Mr, Mrs, etc.'),
            (new ListField('vat_ids', 'vatIds', StringField::class))->addFlags(new ApiAware(), new IgnoreInOpenapiSchema())->setDescription('Unique identity of VAT.'),
            (new StringField('affiliate_code', 'affiliateCode'))->addFlags(new ApiAware())->setDescription('An affiliate code is an identification option with which website operators can mark outgoing links.'),
            (new StringField('campaign_code', 'campaignCode'))->addFlags(new ApiAware())->setDescription('A campaign code is the globally unique identifier for a campaign.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('To keep the status of the customer active, the boolean value is set to `true`.'),
            (new BoolField('double_opt_in_registration', 'doubleOptInRegistration'))->addFlags(new ApiAware())->setDescription('Set to `true` to allow user subscriptions to an email marketing list.'),
            (new DateTimeField('double_opt_in_email_sent_date', 'doubleOptInEmailSentDate'))->addFlags(new ApiAware())->setDescription('Date and time when the double opt-in email was sent.'),
            (new DateTimeField('double_opt_in_confirm_date', 'doubleOptInConfirmDate'))->addFlags(new ApiAware())->setDescription('Date and time when the double opt-in email was confirmed.'),
            (new StringField('hash', 'hash'))->addFlags(new ApiAware())->setDescription('Password hash for customer recovery.'),
            (new BoolField('guest', 'guest'))->addFlags(new ApiAware())->setDescription('Boolean value is `true` if it is to be a guest account.'),
            (new DateTimeField('first_login', 'firstLogin'))->addFlags(new ApiAware())->setDescription('To capture date and time of customer\'s first login.'),
            (new DateTimeField('last_login', 'lastLogin'))->addFlags(new ApiAware())->setDescription('To capture date and time of customer\'s last login.'),
            (new JsonField('newsletter_sales_channel_ids', 'newsletterSalesChannelIds'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->removeFlag(ApiAware::class),
            (new DateField('birthday', 'birthday'))->addFlags(new ApiAware())->setDescription('To capture customer\'s birthday details.'),
            (new DateTimeField('last_order_date', 'lastOrderDate'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Captures last order date.'),
            (new IntField('order_count', 'orderCount'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Captures the number of orders placed.'),
            (new FloatField('order_total_amount', 'orderTotalAmount'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Sum of total amount to be paid.'),
            (new IntField('review_count', 'reviewCount'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Number of reviews the customer has given.'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new StringField('legacy_password', 'legacyPassword'))->removeFlag(ApiAware::class),
            (new StringField('legacy_encoder', 'legacyEncoder'))->removeFlag(ApiAware::class),
            (new ManyToOneAssociationField('group', 'customer_group_id', CustomerGroupDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Customer group determining pricing and permissions'),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            (new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Preferred language for customer communication'),
            (new ManyToOneAssociationField('lastPaymentMethod', 'last_payment_method_id', PaymentMethodDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Last used payment method by the customer'),
            (new ManyToOneAssociationField('defaultBillingAddress', 'default_billing_address_id', CustomerAddressDefinition::class, 'id', false))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING))->setDescription('Default billing address for the customer'),
            (new ManyToOneAssociationField('activeBillingAddress', 'active_billing_address_id', CustomerAddressDefinition::class, 'id', false))->addFlags(new ApiAware(), new Runtime())->setDescription('Currently active billing address in the session'),
            (new ManyToOneAssociationField('defaultShippingAddress', 'default_shipping_address_id', CustomerAddressDefinition::class, 'id', false))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING))->setDescription('Default shipping address for the customer'),
            (new ManyToOneAssociationField('activeShippingAddress', 'active_shipping_address_id', CustomerAddressDefinition::class, 'id', false))->addFlags(new ApiAware(), new Runtime())->setDescription('Currently active shipping address in the session'),
            (new ManyToOneAssociationField('salutation', 'salutation_id', SalutationDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Customer salutation (e.g., Mr., Mrs., Ms.)'),
            (new OneToManyAssociationField('addresses', CustomerAddressDefinition::class, 'customer_id', 'id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('All addresses saved for the customer'),
            (new OneToManyAssociationField('orderCustomers', OrderCustomerDefinition::class, 'customer_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new ManyToManyAssociationField('tags', TagDefinition::class, CustomerTagDefinition::class, 'customer_id', 'tag_id'))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING), new ApiAware())->setDescription('Tags assigned to the customer for organization and segmentation'),
            new ManyToManyAssociationField('promotions', PromotionDefinition::class, PromotionPersonaCustomerDefinition::class, 'customer_id', 'promotion_id'),
            new OneToManyAssociationField('productReviews', ProductReviewDefinition::class, 'customer_id'),
            new OneToOneAssociationField('recoveryCustomer', 'id', 'customer_id', CustomerRecoveryDefinition::class, false),
            (new RemoteAddressField('remote_address', 'remoteAddress'))->setDescription('Anonymous IP address of the customer for last session.'),
            (new ManyToManyIdField('tag_ids', 'tagIds', 'tags'))->addFlags(new ApiAware())->setDescription('Unique identity of tag.'),
            (new FkField('requested_customer_group_id', 'requestedGroupId', CustomerGroupDefinition::class))->setDescription('Unique identity of requested group.'),
            new ManyToOneAssociationField('requestedGroup', 'requested_customer_group_id', CustomerGroupDefinition::class, 'id', false),
            (new FkField('bound_sales_channel_id', 'boundSalesChannelId', SalesChannelDefinition::class))->setDescription('Unique identity of bonus sales channel.'),
            (new StringField('account_type', 'accountType'))->addFlags(new ApiAware(), new Required(), new IgnoreInOpenapiSchema())->setDescription('Account type can be personal or business.'),
            new ManyToOneAssociationField('boundSalesChannel', 'bound_sales_channel_id', SalesChannelDefinition::class, 'id', false),
            (new OneToManyAssociationField('wishlists', CustomerWishlistDefinition::class, 'customer_id'))->addFlags(new CascadeDelete()),
            (new CreatedByField([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE]))->addFlags(new ApiAware()),
            (new UpdatedByField([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE]))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('createdBy', 'created_by_id', UserDefinition::class, 'id', false),
            new ManyToOneAssociationField('updatedBy', 'updated_by_id', UserDefinition::class, 'id', false),
        ]);
    }
}
