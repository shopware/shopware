<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Media\Event\MediaFolderConfigurationIndexerEvent;
use Shopware\Core\Content\Media\Event\MediaFolderIndexerEvent;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;

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
     * @Event("Shopware\Core\Content\Media\Event\MediaIndexerEvent")
     */
    public const MEDIA_INDEXER_EVENT = MediaIndexerEvent::class;

    /**
     * @Event("Shopware\Core\Content\Media\Event\MediaFolderConfigurationIndexerEvent")
     */
    public const MEDIA_FOLDER_CONFIGURATION_INDEXER_EVENT = MediaFolderConfigurationIndexerEvent::class;

    /**
     * @Event("Shopware\Core\Content\Media\Event\MediaFolderIndexerEvent")
     */
    public const MEDIA_FOLDER_INDEXER_EVENT = MediaFolderIndexerEvent::class;

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
