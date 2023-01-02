<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
class DocumentEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var string
     */
    protected $orderVersionId;

    /**
     * @var string
     */
    protected $documentTypeId;

    /**
     * @var string
     */
    protected $documentMediaFileId;

    /**
     * @var string
     */
    protected $fileType;

    /**
     * @var OrderEntity|null
     */
    protected $order;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var bool
     */
    protected $sent;

    /**
     * @var bool
     */
    protected $static;

    /**
     * @var string
     */
    protected $deepLinkCode;

    /**
     * @var DocumentTypeEntity|null
     */
    protected $documentType;

    /**
     * @var string|null
     */
    protected $referencedDocumentId;

    /**
     * @var DocumentEntity|null
     */
    protected $referencedDocument;

    /**
     * @var DocumentCollection|null
     */
    protected $dependentDocuments;

    /**
     * @var MediaEntity
     */
    protected $documentMediaFile;

    public function getFileType(): string
    {
        return $this->fileType;
    }

    public function setFileType(string $fileType): void
    {
        $this->fileType = $fileType;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        $this->orderVersionId = $orderVersionId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getSent(): bool
    {
        return $this->sent;
    }

    public function setSent(bool $sent): void
    {
        $this->sent = $sent;
    }

    public function getDeepLinkCode(): string
    {
        return $this->deepLinkCode;
    }

    public function setDeepLinkCode(string $deepLinkCode): void
    {
        $this->deepLinkCode = $deepLinkCode;
    }

    public function getDocumentType(): ?DocumentTypeEntity
    {
        return $this->documentType;
    }

    public function setDocumentType(DocumentTypeEntity $documentType): void
    {
        $this->documentType = $documentType;
    }

    public function getDocumentTypeId(): string
    {
        return $this->documentTypeId;
    }

    public function setDocumentTypeId(string $documentTypeId): void
    {
        $this->documentTypeId = $documentTypeId;
    }

    public function getReferencedDocumentId(): ?string
    {
        return $this->referencedDocumentId;
    }

    public function setReferencedDocumentId(?string $referencedDocumentId): void
    {
        $this->referencedDocumentId = $referencedDocumentId;
    }

    public function getReferencedDocument(): ?DocumentEntity
    {
        return $this->referencedDocument;
    }

    public function setReferencedDocument(?DocumentEntity $referencedDocument): void
    {
        $this->referencedDocument = $referencedDocument;
    }

    public function getDependentDocuments(): ?DocumentCollection
    {
        return $this->dependentDocuments;
    }

    public function setDependentDocuments(DocumentCollection $dependentDocuments): void
    {
        $this->dependentDocuments = $dependentDocuments;
    }

    public function isStatic(): bool
    {
        return $this->static;
    }

    public function setStatic(bool $static): void
    {
        $this->static = $static;
    }

    public function getDocumentMediaFile(): ?MediaEntity
    {
        return $this->documentMediaFile;
    }

    public function setDocumentMediaFile(?MediaEntity $documentMediaFile): void
    {
        $this->documentMediaFile = $documentMediaFile;
    }

    public function getDocumentMediaFileId(): ?string
    {
        return $this->documentMediaFileId;
    }

    public function setDocumentMediaFileId(?string $documentMediaFileId): void
    {
        $this->documentMediaFileId = $documentMediaFileId;
    }
}
