<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog;

use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class ImportExportLogEvents
{
    final public const IMPORT_EXPORT_LOG_WRITTEN_EVENT = 'import_export_log.written';

    final public const IMPORT_EXPORT_LOG_DELETED_EVENT = 'import_export_log.deleted';

    final public const IMPORT_EXPORT_LOG_LOADED_EVENT = 'import_export_log.loaded';

    final public const IMPORT_EXPORT_LOG_SEARCH_RESULT_LOADED_EVENT = 'import_export_log.search.result.loaded';

    final public const IMPORT_EXPORT_LOG_AGGREGATION_LOADED_EVENT = 'import_export_log.aggregation.result.loaded';

    final public const IMPORT_EXPORT_LOG_ID_SEARCH_RESULT_LOADED_EVENT = 'import_export_log.id.search.result.loaded';
}
