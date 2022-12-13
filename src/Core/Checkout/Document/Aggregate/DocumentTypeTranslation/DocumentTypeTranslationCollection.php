<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package customer-order
 *
 * @extends EntityCollection<DocumentTypeTranslationEntity>
 */
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
