<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
readonly class DocumentGenerationRequest
{
    /**
     * @var list<string>
     */
    public array $requestedFormats;

    public string $documentType;

    /**
     * @param list<DocumentFormat|string> $requestedFormats
     */
    public function __construct(
        public string $orderId,
        public string $orderVersionId,
        DocumentType|string $documentType,
        array $requestedFormats,
        public Context $apiContext,
        public ?string $documentNumber = null,
    ) {
        $this->documentType = $documentType instanceof DocumentType ? $documentType->value : $documentType;
        $this->requestedFormats = array_map(
            static fn (DocumentFormat|string $f) => $f instanceof DocumentFormat ? $f->value : $f,
            $requestedFormats,
        );
    }
}
