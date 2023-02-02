<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Pipe;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;

abstract class AbstractPipeFactory
{
    abstract public function create(ImportExportLogEntity $logEntity): AbstractPipe;

    abstract public function supports(ImportExportLogEntity $logEntity): bool;
}
