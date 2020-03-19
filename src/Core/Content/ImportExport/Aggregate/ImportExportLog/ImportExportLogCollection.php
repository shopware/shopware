<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ImportExportLogCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'import_export_profile_log_collection';
    }

    protected function getExpectedClass(): string
    {
        return ImportExportLogEntity::class;
    }
}
