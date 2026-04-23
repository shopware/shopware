<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
readonly class DocumentGenerationContext
{
    /**
     * @param list<string> $formats
     */
    public function __construct(
        public string $orderId,
        public string $orderVersionId,
        public string $documentType,
        public array $formats,
        public Context $apiContext,
        public ?string $documentNumber = null,
    ) {
    }
}
