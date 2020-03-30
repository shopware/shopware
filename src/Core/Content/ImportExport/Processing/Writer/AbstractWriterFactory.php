<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Writer;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;

abstract class AbstractWriterFactory
{
    abstract public function create(ImportExportLogEntity $logEntity): AbstractWriter;

    abstract public function supports(ImportExportLogEntity $logEntity): bool;
}
