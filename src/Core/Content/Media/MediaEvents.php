<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Media\Event\MediaFolderConfigurationIndexerEvent;
use Shopware\Core\Content\Media\Event\MediaFolderIndexerEvent;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class MediaEvents
{
    final public const MEDIA_WRITTEN_EVENT = 'media.written';

    final public const MEDIA_DELETED_EVENT = 'media.deleted';

    final public const MEDIA_LOADED_EVENT = 'media.loaded';

    public const MEDIA_UPDATED_EVENT = MediaUploadedEvent::EVENT_NAME;

    final public const MEDIA_SEARCH_RESULT_LOADED_EVENT = 'media.search.result.loaded';

    final public const MEDIA_AGGREGATION_LOADED_EVENT = 'media.aggregation.result.loaded';

    final public const MEDIA_INDEXER_EVENT = MediaIndexerEvent::class;

    final public const MEDIA_FOLDER_CONFIGURATION_INDEXER_EVENT = MediaFolderConfigurationIndexerEvent::class;

    final public const MEDIA_FOLDER_INDEXER_EVENT = MediaFolderIndexerEvent::class;

    final public const MEDIA_ID_SEARCH_RESULT_LOADED_EVENT = 'media.id.search.result.loaded';

    final public const MEDIA_TRANSLATION_WRITTEN_EVENT = 'media_translation.written';

    final public const MEDIA_TRANSLATION_DELETED_EVENT = 'media_translation.deleted';

    final public const MEDIA_TRANSLATION_LOADED_EVENT = 'media_translation.loaded';

    final public const MEDIA_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'media_translation.search.result.loaded';

    final public const MEDIA_TRANSLATION_AGGREGATION_LOADED_EVENT = 'media_translation.aggregation.result.loaded';

    final public const MEDIA_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'media_translation.id.search.result.loaded';
}
