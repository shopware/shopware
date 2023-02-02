<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<DocumentEntity>
 */
class DocumentCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'document_collection';
    }

    protected function getExpectedClass(): string
    {
        return DocumentEntity::class;
    }
}
