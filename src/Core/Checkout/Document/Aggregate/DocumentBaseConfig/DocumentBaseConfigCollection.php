<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Deprecation\BCChange\NamespaceChange;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentBaseConfigEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[NamespaceChange(version: 'v6.9.0', newLocation: 'Shopware\\Core\\Checkout\\DocumentV2\\Aggregate\\DocumentBaseConfig\\DocumentBaseConfigCollection')]
class DocumentBaseConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'document_base_collection';
    }

    protected function getExpectedClass(): string
    {
        return DocumentBaseConfigEntity::class;
    }
}
