<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Shared input handed to all data providers during one generation run.
 *
 * @experimental stableVersion:v6.8.0 feature:DOCUMENT_GENERATION_REWORK
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final readonly class ProviderInput
{
    public function __construct(
        public OrderEntity $order,
        public DocumentGenerationRequest $generationRequest,
        public ?ReferencedDocument $resolvedReference = null,
    ) {
    }
}
