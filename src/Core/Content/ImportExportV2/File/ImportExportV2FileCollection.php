<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\File;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ImportExportV2FileEntity>
 */
#[Package('fundamentals@after-sales')]
class ImportExportV2FileCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ImportExportV2FileEntity::class;
    }
}
