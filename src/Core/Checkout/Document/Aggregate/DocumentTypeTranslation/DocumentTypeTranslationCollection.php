<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentTypeTranslationEntity>
 *
 * @codeCoverageIgnore
 *
 * @deprecated tag:v6.9.0 - Will be removed
 */
#[Package('after-sales')]
class DocumentTypeTranslationCollection extends EntityCollection
{
    /**
     * @param iterable<DocumentTypeTranslationEntity> $elements
     */
    public function __construct(iterable $elements = [])
    {
        Feature::throwIfActive('v6.9.0.0', Feature::deprecatedClassMessage(self::class, 'v6.9.0.0'));

        parent::__construct($elements);
    }

    public function getApiAlias(): string
    {
        return 'document_type_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return DocumentTypeTranslationEntity::class;
    }
}
