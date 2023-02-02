<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Reader;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class CsvReaderFactory extends AbstractReaderFactory
{
    public function create(ImportExportLogEntity $logEntity): AbstractReader
    {
        return new CsvReader();
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return $logEntity->getProfile()->getFileType() === 'text/csv';
    }
}
