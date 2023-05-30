<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ShippingEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const SHIPPING_METHOD_WRITTEN_EVENT = 'shipping_method.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const SHIPPING_METHOD_DELETED_EVENT = 'shipping_method.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const SHIPPING_METHOD_LOADED_EVENT = 'shipping_method.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const SHIPPING_METHOD_SEARCH_RESULT_LOADED_EVENT = 'shipping_method.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const SHIPPING_METHOD_AGGREGATION_LOADED_EVENT = 'shipping_method.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const SHIPPING_METHOD_ID_SEARCH_RESULT_LOADED_EVENT = 'shipping_method.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const SHIPPING_METHOD_PRICE_WRITTEN_EVENT = 'shipping_method_price.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const SHIPPING_METHOD_PRICE_DELETED_EVENT = 'shipping_method_price.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const SHIPPING_METHOD_PRICE_LOADED_EVENT = 'shipping_method_price.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const SHIPPING_METHOD_PRICE_SEARCH_RESULT_LOADED_EVENT = 'shipping_method_price.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const SHIPPING_METHOD_PRICE_AGGREGATION_LOADED_EVENT = 'shipping_method_price.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const SHIPPING_METHOD_PRICE_ID_SEARCH_RESULT_LOADED_EVENT = 'shipping_method_price.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const SHIPPING_METHOD_TRANSLATION_WRITTEN_EVENT = 'shipping_method_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const SHIPPING_METHOD_TRANSLATION_DELETED_EVENT = 'shipping_method_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const SHIPPING_METHOD_TRANSLATION_LOADED_EVENT = 'shipping_method_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const SHIPPING_METHOD_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'shipping_method_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const SHIPPING_METHOD_TRANSLATION_AGGREGATION_LOADED_EVENT = 'shipping_method_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const SHIPPING_METHOD_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'shipping_method_translation.id.search.result.loaded';
}
