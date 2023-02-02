<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile;

class ImportExportFileEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const IMPORT_EXPORT_FILE_WRITTEN_EVENT = 'import_export_file.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    public const IMPORT_EXPORT_FILE_DELETED_EVENT = 'import_export_file.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    public const IMPORT_EXPORT_FILE_LOADED_EVENT = 'import_export_file.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    public const IMPORT_EXPORT_FILE_SEARCH_RESULT_LOADED_EVENT = 'import_export_file.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    public const IMPORT_EXPORT_FILE_AGGREGATION_LOADED_EVENT = 'import_export_file.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    public const IMPORT_EXPORT_FILE_ID_SEARCH_RESULT_LOADED_EVENT = 'import_export_file.id.search.result.loaded';
}
