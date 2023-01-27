<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Struct;

use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('customer-order')]
final class DocumentGenerateOperation extends Struct
{
    protected ?string $documentId = null;

    protected string $orderVersionId = Defaults::LIVE_VERSION;

    public function __construct(
        protected string $orderId,
        protected string $fileType = FileTypes::PDF,
        protected array $config = [],
        protected ?string $referencedDocumentId = null,
        protected bool $static = false,
        protected bool $preview = false
    ) {
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getFileType(): string
    {
        return $this->fileType;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function isStatic(): bool
    {
        return $this->static;
    }

    public function setReferencedDocumentId(string $referencedDocumentId): void
    {
        $this->referencedDocumentId = $referencedDocumentId;
    }

    public function getReferencedDocumentId(): ?string
    {
        return $this->referencedDocumentId;
    }

    public function isPreview(): bool
    {
        return $this->preview;
    }

    public function getDocumentId(): ?string
    {
        return $this->documentId;
    }

    public function setDocumentId(string $documentId): void
    {
        $this->documentId = $documentId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        $this->orderVersionId = $orderVersionId;
    }
}
