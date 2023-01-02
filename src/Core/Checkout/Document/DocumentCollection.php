<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentEntity>
 */
#[Package('customer-order')]
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
