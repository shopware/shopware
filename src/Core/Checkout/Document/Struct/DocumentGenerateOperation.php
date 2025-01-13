<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Struct;

use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('after-sales')]
final class DocumentGenerateOperation extends Struct
{
    protected ?string $documentId = null;

    protected string $orderVersionId = Defaults::LIVE_VERSION;

    /**
     * @param array<string, mixed> $config
     * @param array<string> $fileTypes
     */
    public function __construct(
        protected string $orderId,
        protected string $fileType = PdfRenderer::FILE_EXTENSION,
        protected array $config = [],
        protected ?string $referencedDocumentId = null,
        protected bool $static = false,
        protected bool $preview = false,
        protected array $fileTypes = [PdfRenderer::FILE_EXTENSION, HtmlRenderer::FILE_EXTENSION]
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string>
     */
    public function getFileTypes(): array
    {
        return $this->fileTypes;
    }

    /**
     * @param array<string> $types
     */
    public function setFileTypes(array $types): void
    {
        $this->fileTypes = $types;
    }
}
