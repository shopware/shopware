<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentBaseConfigEntity>
 */
#[Package('customer-order')]
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
