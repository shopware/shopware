<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog;

use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class ImportExportLogEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const IMPORT_EXPORT_LOG_WRITTEN_EVENT = 'import_export_log.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const IMPORT_EXPORT_LOG_DELETED_EVENT = 'import_export_log.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const IMPORT_EXPORT_LOG_LOADED_EVENT = 'import_export_log.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const IMPORT_EXPORT_LOG_SEARCH_RESULT_LOADED_EVENT = 'import_export_log.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const IMPORT_EXPORT_LOG_AGGREGATION_LOADED_EVENT = 'import_export_log.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const IMPORT_EXPORT_LOG_ID_SEARCH_RESULT_LOADED_EVENT = 'import_export_log.id.search.result.loaded';
}
