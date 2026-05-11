<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<DocumentFileEntity>
 */
#[Package('after-sales')]
class DocumentFileCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DocumentFileEntity::class;
    }
}
