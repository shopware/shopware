<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                add(DocumentEntity $entity)
 * @method void                set(string $key, DocumentEntity $entity)
 * @method DocumentEntity[]    getIterator()
 * @method DocumentEntity[]    getElements()
 * @method DocumentEntity|null get(string $key)
 * @method DocumentEntity|null first()
 * @method DocumentEntity|null last()
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
