<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentTypeTranslationEntity>
 */
#[Package('customer-order')]
class DocumentTypeTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'document_type_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return DocumentTypeTranslationEntity::class;
    }
}
