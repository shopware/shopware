<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:DOCUMENT_GENERATION_REWORK
 *
 * @extends EntityCollection<DocumentFileEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class DocumentFileCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DocumentFileEntity::class;
    }
}
