<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                       add(ImportExportLogEntity $entity)
 * @method void                       set(string $key, ImportExportLogEntity $entity)
 * @method ImportExportLogEntity[]    getIterator()
 * @method ImportExportLogEntity[]    getElements()
 * @method ImportExportLogEntity|null get(string $key)
 * @method ImportExportLogEntity|null first()
 * @method ImportExportLogEntity|null last()
 */
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
