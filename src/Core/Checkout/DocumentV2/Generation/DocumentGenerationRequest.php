<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\Clock;

/**
 * @experimental stableVersion:v6.8.0 feature:DOCUMENT_GENERATION_REWORK
 */
#[Package('after-sales')]
final readonly class DocumentGenerationRequest
{
    /**
     * @var list<string>
     */
    public array $requestedFormats;

    public string $documentType;

    public string $documentDate;

    /**
     * @param list<DocumentFormat|string> $requestedFormats
     */
    public function __construct(
        public string $orderId,
        DocumentType|string $documentType,
        array $requestedFormats,
        public ?string $documentNumber = null,
        public ?string $documentComment = null,
        ?string $documentDate = null,
        public ?string $deliveryDate = null,
        public ?string $referencedDocumentId = null,
    ) {
        $this->documentDate = $documentDate ?? Clock::get()->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->documentType = $documentType instanceof DocumentType ? $documentType->value : $documentType;
        $this->requestedFormats = array_map(
            static fn (DocumentFormat|string $f) => $f instanceof DocumentFormat ? $f->value : $f,
            $requestedFormats,
        );
    }

    public function withDocumentNumber(string $documentNumber): self
    {
        return new self(
            $this->orderId,
            $this->documentType,
            $this->requestedFormats,
            $documentNumber,
            $this->documentComment,
            $this->documentDate,
            $this->deliveryDate,
            $this->referencedDocumentId,
        );
    }
}
