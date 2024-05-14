<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class CustomerEvents
{
    final public const CUSTOMER_WRITTEN_EVENT = 'customer.written';

    final public const CUSTOMER_DELETED_EVENT = 'customer.deleted';

    final public const CUSTOMER_LOADED_EVENT = 'customer.loaded';

    final public const CUSTOMER_SEARCH_RESULT_LOADED_EVENT = 'customer.search.result.loaded';

    final public const CUSTOMER_AGGREGATION_LOADED_EVENT = 'customer.aggregation.result.loaded';

    final public const CUSTOMER_ID_SEARCH_RESULT_LOADED_EVENT = 'customer.id.search.result.loaded';

    final public const CUSTOMER_ADDRESS_WRITTEN_EVENT = 'customer_address.written';

    final public const CUSTOMER_ADDRESS_DELETED_EVENT = 'customer_address.deleted';

    final public const CUSTOMER_ADDRESS_LOADED_EVENT = 'customer_address.loaded';

    final public const CUSTOMER_ADDRESS_SEARCH_RESULT_LOADED_EVENT = 'customer_address.search.result.loaded';

    final public const CUSTOMER_ADDRESS_AGGREGATION_LOADED_EVENT = 'customer_address.aggregation.result.loaded';

    final public const CUSTOMER_ADDRESS_ID_SEARCH_RESULT_LOADED_EVENT = 'customer_address.id.search.result.loaded';

    final public const CUSTOMER_CHANGED_PAYMENT_METHOD_EVENT = 'checkout.customer.changed-payment-method';

    final public const CUSTOMER_BEFORE_LOGIN_EVENT = 'checkout.customer.before.login';

    final public const CUSTOMER_LOGIN_EVENT = 'checkout.customer.login';

    final public const CUSTOMER_LOGOUT_EVENT = 'checkout.customer.logout';

    final public const CUSTOMER_REGISTER_EVENT = 'checkout.customer.register';

    final public const CUSTOMER_GROUP_WRITTEN_EVENT = 'customer_group.written';

    final public const CUSTOMER_GROUP_DELETED_EVENT = 'customer_group.deleted';

    final public const CUSTOMER_GROUP_LOADED_EVENT = 'customer_group.loaded';

    final public const CUSTOMER_GROUP_SEARCH_RESULT_LOADED_EVENT = 'customer_group.search.result.loaded';

    final public const CUSTOMER_GROUP_AGGREGATION_LOADED_EVENT = 'customer_group.aggregation.result.loaded';

    final public const CUSTOMER_GROUP_ID_SEARCH_RESULT_LOADED_EVENT = 'customer_group.id.search.result.loaded';

    final public const CUSTOMER_GROUP_DISCOUNT_WRITTEN_EVENT = 'customer_group_discount.written';

    final public const CUSTOMER_GROUP_DISCOUNT_DELETED_EVENT = 'customer_group_discount.deleted';

    final public const CUSTOMER_GROUP_DISCOUNT_LOADED_EVENT = 'customer_group_discount.loaded';

    final public const CUSTOMER_GROUP_DISCOUNT_SEARCH_RESULT_LOADED_EVENT = 'customer_group_discount.search.result.loaded';

    final public const CUSTOMER_GROUP_DISCOUNT_AGGREGATION_LOADED_EVENT = 'customer_group_discount.aggregation.result.loaded';

    final public const CUSTOMER_GROUP_DISCOUNT_ID_SEARCH_RESULT_LOADED_EVENT = 'customer_group_discount.id.search.result.loaded';

    final public const CUSTOMER_GROUP_TRANSLATION_WRITTEN_EVENT = 'customer_group_translation.written';

    final public const CUSTOMER_GROUP_TRANSLATION_DELETED_EVENT = 'customer_group_translation.deleted';

    final public const CUSTOMER_GROUP_TRANSLATION_LOADED_EVENT = 'customer_group_translation.loaded';

    final public const CUSTOMER_GROUP_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'customer_group_translation.search.result.loaded';

    final public const CUSTOMER_GROUP_TRANSLATION_AGGREGATION_LOADED_EVENT = 'customer_group_translation.aggregation.result.loaded';

    final public const CUSTOMER_GROUP_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'customer_group_translation.id.search.result.loaded';

    final public const MAPPING_REGISTER_ADDRESS_BILLING = 'checkout.customer.sales_channel.register.billing_address';

    final public const MAPPING_REGISTER_ADDRESS_SHIPPING = 'checkout.customer.sales_channel.register.shipping_address';

    final public const MAPPING_REGISTER_CUSTOMER = 'checkout.customer.sales_channel.register.customer';

    final public const MAPPING_CUSTOMER_PROFILE_SAVE = 'checkout.customer.sales_channel.profile.update';

    final public const MAPPING_ADDRESS_CREATE = 'checkout.customer.sales_channel.address.create';
}
