<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * The document another document references, resolved by the generation pipeline.
 *
 * @experimental stableVersion:v6.8.0 feature:DOCUMENT_GENERATION_REWORK
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class ReferencedDocument
{
    public function __construct(
        public string $id,
        public string $documentNumber,
        public string $orderVersionId,
    ) {
    }
}
