<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Run;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ImportExportV2RunEntity>
 */
#[Package('fundamentals@after-sales')]
class ImportExportV2RunCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ImportExportV2RunEntity::class;
    }
}
