<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed.
 *
 * @extends Collection<DocumentIdStruct>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class DocumentIdCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'document_id_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return DocumentIdStruct::class;
    }
}
