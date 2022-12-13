<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package customer-order
 *
 * @extends EntityCollection<DocumentTypeEntity>
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
