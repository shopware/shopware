<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                      add(ImportExportProfileTranslationEntity $entity)
 * @method void                                      set(string $key, ImportExportProfileTranslationEntity $entity)
 * @method ImportExportProfileTranslationEntity[]    getIterator()
 * @method ImportExportProfileTranslationEntity[]    getElements()
 * @method ImportExportProfileTranslationEntity|null get(string $key)
 * @method ImportExportProfileTranslationEntity|null first()
 * @method ImportExportProfileTranslationEntity|null last()
 */
class ImportExportProfileTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ImportExportProfileTranslationEntity::class;
    }
}
