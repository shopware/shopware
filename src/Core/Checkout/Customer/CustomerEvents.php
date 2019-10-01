<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

class CustomerEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const CUSTOMER_WRITTEN_EVENT = 'customer.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const CUSTOMER_DELETED_EVENT = 'customer.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const CUSTOMER_LOADED_EVENT = 'customer.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const CUSTOMER_SEARCH_RESULT_LOADED_EVENT = 'customer.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const CUSTOMER_AGGREGATION_LOADED_EVENT = 'customer.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CUSTOMER_ID_SEARCH_RESULT_LOADED_EVENT = 'customer.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const CUSTOMER_ADDRESS_WRITTEN_EVENT = 'customer_address.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const CUSTOMER_ADDRESS_DELETED_EVENT = 'customer_address.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const CUSTOMER_ADDRESS_LOADED_EVENT = 'customer_address.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const CUSTOMER_ADDRESS_SEARCH_RESULT_LOADED_EVENT = 'customer_address.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const CUSTOMER_ADDRESS_AGGREGATION_LOADED_EVENT = 'customer_address.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CUSTOMER_ADDRESS_ID_SEARCH_RESULT_LOADED_EVENT = 'customer_address.id.search.result.loaded';

    /**
     * @Event("\Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent")
     */
    public const CUSTOMER_CHANGED_PAYMENT_METHOD_EVENT = 'checkout.customer.changed-payment-method';

    /**
     * @Event("\Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent")
     */
    public const CUSTOMER_BEFORE_LOGIN_EVENT = 'checkout.customer.before.login';

    /**
     * @Event("\Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent")
     */
    public const CUSTOMER_LOGIN_EVENT = 'checkout.customer.login';

    /**
     * @Event("\Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent")
     */
    public const CUSTOMER_LOGOUT_EVENT = 'checkout.customer.logout';

    /**
     * @Event("\Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent")
     */
    public const CUSTOMER_REGISTER_EVENT = 'checkout.customer.register';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const CUSTOMER_GROUP_WRITTEN_EVENT = 'customer_group.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const CUSTOMER_GROUP_DELETED_EVENT = 'customer_group.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const CUSTOMER_GROUP_LOADED_EVENT = 'customer_group.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_SEARCH_RESULT_LOADED_EVENT = 'customer_group.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_AGGREGATION_LOADED_EVENT = 'customer_group.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_ID_SEARCH_RESULT_LOADED_EVENT = 'customer_group.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const CUSTOMER_GROUP_DISCOUNT_WRITTEN_EVENT = 'customer_group_discount.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const CUSTOMER_GROUP_DISCOUNT_DELETED_EVENT = 'customer_group_discount.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const CUSTOMER_GROUP_DISCOUNT_LOADED_EVENT = 'customer_group_discount.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_DISCOUNT_SEARCH_RESULT_LOADED_EVENT = 'customer_group_discount.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_DISCOUNT_AGGREGATION_LOADED_EVENT = 'customer_group_discount.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_DISCOUNT_ID_SEARCH_RESULT_LOADED_EVENT = 'customer_group_discount.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const CUSTOMER_GROUP_TRANSLATION_WRITTEN_EVENT = 'customer_group_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const CUSTOMER_GROUP_TRANSLATION_DELETED_EVENT = 'customer_group_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const CUSTOMER_GROUP_TRANSLATION_LOADED_EVENT = 'customer_group_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'customer_group_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_TRANSLATION_AGGREGATION_LOADED_EVENT = 'customer_group_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'customer_group_translation.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\Event\DataMappingEvent")
     */
    public const MAPPING_REGISTER_ADDRESS_BILLING = 'checkout.customer.sales_channel.register.billing_address';

    /**
     * @Event("Shopware\Core\Framework\Event\DataMappingEvent")
     */
    public const MAPPING_REGISTER_ADDRESS_SHIPPING = 'checkout.customer.sales_channel.register.shipping_address';

    /**
     * @Event("Shopware\Core\Framework\Event\DataMappingEvent")
     */
    public const MAPPING_REGISTER_CUSTOMER = 'checkout.customer.sales_channel.register.customer';

    /**
     * @Event("Shopware\Core\Framework\Event\DataMappingEvent")
     */
    public const MAPPING_CUSTOMER_PROFILE_SAVE = 'checkout.customer.sales_channel.profile.update';

    /**
     * @Event("Shopware\Core\Framework\Event\DataMappingEvent")
     */
    public const MAPPING_ADDRESS_CREATE = 'checkout.customer.sales_channel.address.create';
}
