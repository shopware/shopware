<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class ImportExportProfileEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const IMPORT_EXPORT_PROFILE_WRITTEN_EVENT = 'import_export_profile.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const IMPORT_EXPORT_PROFILE_DELETED_EVENT = 'import_export_profile.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const IMPORT_EXPORT_PROFILE_LOADED_EVENT = 'import_export_profile.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const IMPORT_EXPORT_PROFILE_SEARCH_RESULT_LOADED_EVENT = 'import_export_profile.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const IMPORT_EXPORT_PROFILE_AGGREGATION_LOADED_EVENT = 'import_export_profile.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const IMPORT_EXPORT_PROFILE_ID_SEARCH_RESULT_LOADED_EVENT = 'import_export_profile.id.search.result.loaded';
}
