<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ShippingEvents
{
    final public const SHIPPING_METHOD_WRITTEN_EVENT = 'shipping_method.written';

    final public const SHIPPING_METHOD_DELETED_EVENT = 'shipping_method.deleted';

    final public const SHIPPING_METHOD_LOADED_EVENT = 'shipping_method.loaded';

    final public const SHIPPING_METHOD_SEARCH_RESULT_LOADED_EVENT = 'shipping_method.search.result.loaded';

    final public const SHIPPING_METHOD_AGGREGATION_LOADED_EVENT = 'shipping_method.aggregation.result.loaded';

    final public const SHIPPING_METHOD_ID_SEARCH_RESULT_LOADED_EVENT = 'shipping_method.id.search.result.loaded';

    final public const SHIPPING_METHOD_PRICE_WRITTEN_EVENT = 'shipping_method_price.written';

    final public const SHIPPING_METHOD_PRICE_DELETED_EVENT = 'shipping_method_price.deleted';

    final public const SHIPPING_METHOD_PRICE_LOADED_EVENT = 'shipping_method_price.loaded';

    final public const SHIPPING_METHOD_PRICE_SEARCH_RESULT_LOADED_EVENT = 'shipping_method_price.search.result.loaded';

    final public const SHIPPING_METHOD_PRICE_AGGREGATION_LOADED_EVENT = 'shipping_method_price.aggregation.result.loaded';

    final public const SHIPPING_METHOD_PRICE_ID_SEARCH_RESULT_LOADED_EVENT = 'shipping_method_price.id.search.result.loaded';

    final public const SHIPPING_METHOD_TRANSLATION_WRITTEN_EVENT = 'shipping_method_translation.written';

    final public const SHIPPING_METHOD_TRANSLATION_DELETED_EVENT = 'shipping_method_translation.deleted';

    final public const SHIPPING_METHOD_TRANSLATION_LOADED_EVENT = 'shipping_method_translation.loaded';

    final public const SHIPPING_METHOD_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'shipping_method_translation.search.result.loaded';

    final public const SHIPPING_METHOD_TRANSLATION_AGGREGATION_LOADED_EVENT = 'shipping_method_translation.aggregation.result.loaded';

    final public const SHIPPING_METHOD_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'shipping_method_translation.id.search.result.loaded';
}
