<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                    add(DocumentTypeEntity $entity)
 * @method void                    set(string $key, DocumentTypeEntity $entity)
 * @method DocumentTypeEntity[]    getIterator()
 * @method DocumentTypeEntity[]    getElements()
 * @method DocumentTypeEntity|null get(string $key)
 * @method DocumentTypeEntity|null first()
 * @method DocumentTypeEntity|null last()
 */
class DocumentTypeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'document_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return DocumentTypeEntity::class;
    }
}
