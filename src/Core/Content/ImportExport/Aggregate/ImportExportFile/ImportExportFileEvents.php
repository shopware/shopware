<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class ImportExportFileEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const IMPORT_EXPORT_FILE_WRITTEN_EVENT = 'import_export_file.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const IMPORT_EXPORT_FILE_DELETED_EVENT = 'import_export_file.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const IMPORT_EXPORT_FILE_LOADED_EVENT = 'import_export_file.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const IMPORT_EXPORT_FILE_SEARCH_RESULT_LOADED_EVENT = 'import_export_file.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const IMPORT_EXPORT_FILE_AGGREGATION_LOADED_EVENT = 'import_export_file.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const IMPORT_EXPORT_FILE_ID_SEARCH_RESULT_LOADED_EVENT = 'import_export_file.id.search.result.loaded';
}
