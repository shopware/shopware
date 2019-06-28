<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Writer;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\Context;

interface WriterFactoryInterface
{
    public function create(Context $context, ImportExportLogEntity $logEntity): WriterInterface;

    public function supports(ImportExportLogEntity $logEntity): bool;
}
