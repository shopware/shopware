<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed
 *
 * @extends EntityCollection<ImportExportProfileTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class ImportExportProfileTranslationCollection extends EntityCollection
{
    /**
     * @param iterable<ImportExportProfileTranslationEntity> $elements
     */
    public function __construct(iterable $elements = [])
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct($elements);
    }

    protected function getExpectedClass(): string
    {
        return ImportExportProfileTranslationEntity::class;
    }
}
