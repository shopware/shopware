<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Profile;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ImportExportV2ProfileEntity>
 */
#[Package('fundamentals@after-sales')]
class ImportExportV2ProfileCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ImportExportV2ProfileEntity::class;
    }
}
