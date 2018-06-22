<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

class CustomerEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const CUSTOMER_WRITTEN_EVENT = 'customer.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const CUSTOMER_DELETED_EVENT = 'customer.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const CUSTOMER_LOADED_EVENT = 'customer.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const CUSTOMER_SEARCH_RESULT_LOADED_EVENT = 'customer.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const CUSTOMER_AGGREGATION_LOADED_EVENT = 'customer.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CUSTOMER_ID_SEARCH_RESULT_LOADED_EVENT = 'customer.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const CUSTOMER_ADDRESS_WRITTEN_EVENT = 'customer_address.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const CUSTOMER_ADDRESS_DELETED_EVENT = 'customer_address.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const CUSTOMER_ADDRESS_LOADED_EVENT = 'customer_address.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const CUSTOMER_ADDRESS_SEARCH_RESULT_LOADED_EVENT = 'customer_address.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const CUSTOMER_ADDRESS_AGGREGATION_LOADED_EVENT = 'customer_address.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CUSTOMER_ADDRESS_ID_SEARCH_RESULT_LOADED_EVENT = 'customer_address.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const CUSTOMER_GROUP_WRITTEN_EVENT = 'customer_group.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const CUSTOMER_GROUP_DELETED_EVENT = 'customer_group.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const CUSTOMER_GROUP_LOADED_EVENT = 'customer_group.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_SEARCH_RESULT_LOADED_EVENT = 'customer_group.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_AGGREGATION_LOADED_EVENT = 'customer_group.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_ID_SEARCH_RESULT_LOADED_EVENT = 'customer_group.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const CUSTOMER_GROUP_DISCOUNT_WRITTEN_EVENT = 'customer_group_discount.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const CUSTOMER_GROUP_DISCOUNT_DELETED_EVENT = 'customer_group_discount.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const CUSTOMER_GROUP_DISCOUNT_LOADED_EVENT = 'customer_group_discount.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_DISCOUNT_SEARCH_RESULT_LOADED_EVENT = 'customer_group_discount.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_DISCOUNT_AGGREGATION_LOADED_EVENT = 'customer_group_discount.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_DISCOUNT_ID_SEARCH_RESULT_LOADED_EVENT = 'customer_group_discount.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const CUSTOMER_GROUP_TRANSLATION_WRITTEN_EVENT = 'customer_group_translation.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const CUSTOMER_GROUP_TRANSLATION_DELETED_EVENT = 'customer_group_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const CUSTOMER_GROUP_TRANSLATION_LOADED_EVENT = 'customer_group_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'customer_group_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_TRANSLATION_AGGREGATION_LOADED_EVENT = 'customer_group_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const CUSTOMER_GROUP_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'customer_group_translation.id.search.result.loaded';
}
