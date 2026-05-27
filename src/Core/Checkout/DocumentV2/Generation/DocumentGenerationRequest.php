<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\Clock;

/**
 * @internal
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
        public string $orderVersionId,
        DocumentType|string $documentType,
        array $requestedFormats,
        public ?string $documentNumber = null,
        public ?string $documentComment = null,
        ?string $documentDate = null,
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
            $this->orderVersionId,
            $this->documentType,
            $this->requestedFormats,
            $documentNumber,
            $this->documentComment,
            $this->documentDate,
        );
    }
}
