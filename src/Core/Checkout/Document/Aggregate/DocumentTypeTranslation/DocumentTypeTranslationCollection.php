<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                               add(DocumentTypeTranslationEntity $entity)
 * @method void                               set(string $key, DocumentTypeTranslationEntity $entity)
 * @method DocumentTypeTranslationEntity[]    getIterator()
 * @method DocumentTypeTranslationEntity[]    getElements()
 * @method DocumentTypeTranslationEntity|null get(string $key)
 * @method DocumentTypeTranslationEntity|null first()
 * @method DocumentTypeTranslationEntity|null last()
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
