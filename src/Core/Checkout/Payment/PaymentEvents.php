<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

class PaymentEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const PAYMENT_METHOD_WRITTEN_EVENT = 'payment_method.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const PAYMENT_METHOD_DELETED_EVENT = 'payment_method.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const PAYMENT_METHOD_LOADED_EVENT = 'payment_method.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const PAYMENT_METHOD_SEARCH_RESULT_LOADED_EVENT = 'payment_method.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const PAYMENT_METHOD_AGGREGATION_LOADED_EVENT = 'payment_method.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PAYMENT_METHOD_ID_SEARCH_RESULT_LOADED_EVENT = 'payment_method.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const PAYMENT_METHOD_TRANSLATION_WRITTEN_EVENT = 'payment_method_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const PAYMENT_METHOD_TRANSLATION_DELETED_EVENT = 'payment_method_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const PAYMENT_METHOD_TRANSLATION_LOADED_EVENT = 'payment_method_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const PAYMENT_METHOD_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'payment_method_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const PAYMENT_METHOD_TRANSLATION_AGGREGATION_LOADED_EVENT = 'payment_method_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const PAYMENT_METHOD_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'payment_method_translation.id.search.result.loaded';
}
