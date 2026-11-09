<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ImportExportFileEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class ImportExportFileCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'import_export_file_collection';
    }

    protected function getExpectedClass(): string
    {
        return ImportExportFileEntity::class;
    }
}
