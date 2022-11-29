<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Struct;

use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @package customer-order
 */
final class DocumentGenerateOperation extends Struct
{
    protected string $orderId;

    protected string $fileType;

    protected array $config;

    protected bool $static;

    protected ?string $referencedDocumentId;

    protected bool $preview;

    protected ?string $documentId = null;

    protected string $orderVersionId = Defaults::LIVE_VERSION;

    public function __construct(
        string $orderId,
        string $fileType = FileTypes::PDF,
        array $config = [],
        ?string $referencedDocumentId = null,
        bool $static = false,
        bool $preview = false
    ) {
        $this->orderId = $orderId;
        $this->fileType = $fileType;
        $this->config = $config;
        $this->referencedDocumentId = $referencedDocumentId;
        $this->static = $static;
        $this->preview = $preview;
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
