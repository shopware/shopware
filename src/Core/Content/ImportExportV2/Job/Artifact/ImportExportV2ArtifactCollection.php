<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Job\Artifact;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ImportExportV2ArtifactEntity>
 */
#[Package('fundamentals@after-sales')]
class ImportExportV2ArtifactCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ImportExportV2ArtifactEntity::class;
    }
}
