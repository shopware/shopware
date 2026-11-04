<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<DocumentIdStruct>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    description: 'Part of the legacy document generation pipeline. DocumentV2 handles this concern internally and exposes no counterpart.',
)]
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
