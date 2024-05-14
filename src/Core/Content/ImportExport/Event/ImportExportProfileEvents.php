<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class ImportExportProfileEvents
{
    final public const IMPORT_EXPORT_PROFILE_WRITTEN_EVENT = 'import_export_profile.written';

    final public const IMPORT_EXPORT_PROFILE_DELETED_EVENT = 'import_export_profile.deleted';

    final public const IMPORT_EXPORT_PROFILE_LOADED_EVENT = 'import_export_profile.loaded';

    final public const IMPORT_EXPORT_PROFILE_SEARCH_RESULT_LOADED_EVENT = 'import_export_profile.search.result.loaded';

    final public const IMPORT_EXPORT_PROFILE_AGGREGATION_LOADED_EVENT = 'import_export_profile.aggregation.result.loaded';

    final public const IMPORT_EXPORT_PROFILE_ID_SEARCH_RESULT_LOADED_EVENT = 'import_export_profile.id.search.result.loaded';
}
