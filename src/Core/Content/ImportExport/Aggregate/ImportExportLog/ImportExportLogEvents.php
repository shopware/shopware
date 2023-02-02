<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog;

class ImportExportLogEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const IMPORT_EXPORT_LOG_WRITTEN_EVENT = 'import_export_log.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const IMPORT_EXPORT_LOG_DELETED_EVENT = 'import_export_log.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const IMPORT_EXPORT_LOG_LOADED_EVENT = 'import_export_log.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const IMPORT_EXPORT_LOG_SEARCH_RESULT_LOADED_EVENT = 'import_export_log.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const IMPORT_EXPORT_LOG_AGGREGATION_LOADED_EVENT = 'import_export_log.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const IMPORT_EXPORT_LOG_ID_SEARCH_RESULT_LOADED_EVENT = 'import_export_log.id.search.result.loaded';
}
