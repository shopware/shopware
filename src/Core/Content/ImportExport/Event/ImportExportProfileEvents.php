<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

class ImportExportProfileEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const IMPORT_EXPORT_PROFILE_WRITTEN_EVENT = 'import_export_profile.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const IMPORT_EXPORT_PROFILE_DELETED_EVENT = 'import_export_profile.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const IMPORT_EXPORT_PROFILE_LOADED_EVENT = 'import_export_profile.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const IMPORT_EXPORT_PROFILE_SEARCH_RESULT_LOADED_EVENT = 'import_export_profile.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const IMPORT_EXPORT_PROFILE_AGGREGATION_LOADED_EVENT = 'import_export_profile.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const IMPORT_EXPORT_PROFILE_ID_SEARCH_RESULT_LOADED_EVENT = 'import_export_profile.id.search.result.loaded';
}
