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
        private string $orderId,
        private string $orderVersionId,
        private string $documentType,
        private array $formats,
        private Context $context,
        private ?string $documentNumber = null,
    ) {
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    /**
     * @return list<string>
     */
    public function getFormats(): array
    {
        return $this->formats;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getDocumentNumber(): ?string
    {
        return $this->documentNumber;
    }
}
