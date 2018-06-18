<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

class MediaEvents
{
    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const MEDIA_WRITTEN_EVENT = 'media.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const MEDIA_DELETED_EVENT = 'media.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const MEDIA_LOADED_EVENT = 'media.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const MEDIA_SEARCH_RESULT_LOADED_EVENT = 'media.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const MEDIA_AGGREGATION_LOADED_EVENT = 'media.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const MEDIA_ID_SEARCH_RESULT_LOADED_EVENT = 'media.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const MEDIA_ALBUM_WRITTEN_EVENT = 'media_album.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const MEDIA_ALBUM_DELETED_EVENT = 'media_album.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const MEDIA_ALBUM_LOADED_EVENT = 'media_album.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const MEDIA_ALBUM_SEARCH_RESULT_LOADED_EVENT = 'media_album.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const MEDIA_ALBUM_AGGREGATION_LOADED_EVENT = 'media_album.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const MEDIA_ALBUM_ID_SEARCH_RESULT_LOADED_EVENT = 'media_album.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const MEDIA_ALBUM_TRANSLATION_WRITTEN_EVENT = 'media_album_translation.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const MEDIA_ALBUM_TRANSLATION_DELETED_EVENT = 'media_album_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const MEDIA_ALBUM_TRANSLATION_LOADED_EVENT = 'media_album_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const MEDIA_ALBUM_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'media_album_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const MEDIA_ALBUM_TRANSLATION_AGGREGATION_LOADED_EVENT = 'media_album_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const MEDIA_ALBUM_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'media_album_translation.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityWrittenEvent")
     */
    public const MEDIA_TRANSLATION_WRITTEN_EVENT = 'media_translation.written';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityDeletedEvent")
     */
    public const MEDIA_TRANSLATION_DELETED_EVENT = 'media_translation.deleted';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityLoadedEvent")
     */
    public const MEDIA_TRANSLATION_LOADED_EVENT = 'media_translation.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntitySearchResultLoadedEvent")
     */
    public const MEDIA_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'media_translation.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityAggregationResultLoadedEvent")
     */
    public const MEDIA_TRANSLATION_AGGREGATION_LOADED_EVENT = 'media_translation.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\ORM\Event\EntityIdSearchResultLoadedEvent")
     */
    public const MEDIA_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'media_translation.id.search.result.loaded';
}