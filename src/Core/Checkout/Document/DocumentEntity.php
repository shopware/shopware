<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
class DocumentEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $orderId;

    protected string $orderVersionId;

    protected string $documentTypeId;

    protected ?string $documentMediaFileId = null;

    protected ?OrderEntity $order = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    protected bool $sent;

    protected bool $static;

    protected string $deepLinkCode;

    protected ?DocumentTypeEntity $documentType = null;

    protected ?string $referencedDocumentId = null;

    protected ?DocumentEntity $referencedDocument = null;

    protected ?DocumentCollection $dependentDocuments = null;

    protected ?MediaEntity $documentMediaFile = null;

    protected ?string $documentNumber = null;

    protected ?string $documentA11yMediaFileId = null;

    protected ?MediaEntity $documentA11yMediaFile = null;

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

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     */
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

    public function setDocumentNumber(?string $documentNumber): void
    {
        $this->documentNumber = $documentNumber;
    }

    public function getDocumentNumber(): ?string
    {
        return $this->documentNumber;
    }

    public function getDocumentA11yMediaFileId(): ?string
    {
        return $this->documentA11yMediaFileId;
    }

    public function setDocumentA11yMediaFileId(?string $mediaFileId): void
    {
        $this->documentA11yMediaFileId = $mediaFileId;
    }

    public function getDocumentA11yMediaFile(): ?MediaEntity
    {
        return $this->documentA11yMediaFile;
    }

    public function setDocumentA11yMediaFile(?MediaEntity $mediaEntity): void
    {
        $this->documentA11yMediaFile = $mediaEntity;
    }
}
