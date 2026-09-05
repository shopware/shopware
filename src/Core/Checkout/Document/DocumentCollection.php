<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Deprecation\BCChange\NamespaceChange;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[NamespaceChange(version: 'v6.9.0', newLocation: 'Shopware\\Core\\Checkout\\DocumentV2\\DocumentCollection')]
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
