<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentTypeEntity>
 *
 * @codeCoverageIgnore
 *
 * @deprecated tag:v6.9.0 reason:remove-entity - Will be removed. Use `document.type_name` instead.
 */
#[Package('after-sales')]
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
