<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentMedia;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentMediaEntity>
 */
#[Package('checkout')]
class DocumentMediaCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'document_media_collection';
    }

    protected function getExpectedClass(): string
    {
        return DocumentMediaEntity::class;
    }
}
