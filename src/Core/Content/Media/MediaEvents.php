<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

class MediaEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const MEDIA_WRITTEN_EVENT = 'media.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const MEDIA_DELETED_EVENT = 'media.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const MEDIA_LOADED_EVENT = 'media.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const MEDIA_SEARCH_RESULT_LOADED_EVENT = 'media.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const MEDIA_AGGREGATION_LOADED_EVENT = 'media.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const MEDIA_ID_SEARCH_RESULT_LOADED_EVENT = 'media.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const MEDIA_TRANSLATION_WRITTEN_EVENT = 'media_translation.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const MEDIA_TRANSLATION_DELETED_EVENT = 'media_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const MEDIA_TRANSLATION_LOADED_EVENT = 'media_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const MEDIA_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'media_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const MEDIA_TRANSLATION_AGGREGATION_LOADED_EVENT = 'media_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const MEDIA_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'media_translation.id.search.result.loaded';
}
