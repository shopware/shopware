<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
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

/** @deprecated tag:v6.9.0 - compatibility alias */
class_alias(DocumentCollection::class, 'Shopware\Core\Checkout\Document\DocumentCollection');
