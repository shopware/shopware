<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Iterator;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\Context;

interface IteratorFactoryInterface
{
    public function create(Context $context, ImportExportLogEntity $logEntity): RecordIterator;

    public function supports(ImportExportLogEntity $logEntity): bool;
}
