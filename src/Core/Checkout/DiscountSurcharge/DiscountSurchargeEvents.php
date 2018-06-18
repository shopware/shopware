<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge;

class DiscountSurchargeEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const DISCOUNT_SURCHARGE_WRITTEN_EVENT = 'discount_surcharge.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const DISCOUNT_SURCHARGE_DELETED_EVENT = 'discount_surcharge.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const DISCOUNT_SURCHARGE_LOADED_EVENT = 'discount_surcharge.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const DISCOUNT_SURCHARGE_SEARCH_RESULT_LOADED_EVENT = 'discount_surcharge.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const DISCOUNT_SURCHARGE_AGGREGATION_LOADED_EVENT = 'discount_surcharge.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const DISCOUNT_SURCHARGE_ID_SEARCH_RESULT_LOADED_EVENT = 'discount_surcharge.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const DISCOUNT_SURCHARGE_TRANSLATION_WRITTEN_EVENT = 'discount_surcharge_translation.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const DISCOUNT_SURCHARGE_TRANSLATION_DELETED_EVENT = 'discount_surcharge_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const DISCOUNT_SURCHARGE_TRANSLATION_LOADED_EVENT = 'discount_surcharge_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const DISCOUNT_SURCHARGE_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'discount_surcharge_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const DISCOUNT_SURCHARGE_TRANSLATION_AGGREGATION_LOADED_EVENT = 'discount_surcharge_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const DISCOUNT_SURCHARGE_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'discount_surcharge_translation.id.search.result.loaded';
}