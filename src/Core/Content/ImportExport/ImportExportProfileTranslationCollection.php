<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ImportExportProfileTranslationEntity>
 */
class ImportExportProfileTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ImportExportProfileTranslationEntity::class;
    }
}
