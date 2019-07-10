<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Iterator;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Framework\Context;

interface IteratorFactoryInterface
{
    public function create(Context $context, string $activity, ImportExportProfileEntity $profileEntity, ImportExportFileEntity $fileEntity): RecordIterator;

    public function supports(string $activity, ImportExportProfileEntity $profileEntity): bool;
}
