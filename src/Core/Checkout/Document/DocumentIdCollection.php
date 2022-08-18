<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void                add(DocumentIdStruct $entity)
 * @method void                set(string $key, DocumentIdStruct $entity)
 * @method DocumentIdStruct[]    getIterator()
 * @method DocumentIdStruct[]    getElements()
 * @method DocumentIdStruct|null get(string $key)
 * @method DocumentIdStruct|null first()
 * @method DocumentIdStruct|null last()
 */
class DocumentIdCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'document_id_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return DocumentIdStruct::class;
    }
}
