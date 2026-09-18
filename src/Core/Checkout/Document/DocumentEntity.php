<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeWidening;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeWidening;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class DocumentEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @deprecated tag:v6.8.0 - Type will change to ?string.
     */
    protected string $orderId;

    /**
     * @deprecated tag:v6.8.0 - Type will change to ?string.
     */
    protected string $orderVersionId;

    protected string $documentTypeId;

    protected ?string $typeName = null;

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

    /**
     * @internal
     */
    protected ?DocumentFileCollection $documentFiles = null;

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    #[ReturnTypeWidening(version: 'v6.8.0', newType: '?string', description: 'Will return null for documents without order.')]
    public function getOrderVersionId(): string
    {
        return $this->orderVersionId ?? '';
    }

    #[ParameterTypeWidening(version: 'v6.8.0', parameterName: 'orderVersionId', newType: '?string', description: 'Will accept null for documents without order.')]
    public function setOrderVersionId(string $orderVersionId): void
    {
        $this->orderVersionId = $orderVersionId;
    }

    #[ReturnTypeWidening(version: 'v6.8.0', newType: '?string', description: 'Will return null for documents without order.')]
    public function getOrderId(): string
    {
        return $this->orderId ?? '';
    }

    #[ParameterTypeWidening(version: 'v6.8.0', parameterName: 'orderId', newType: '?string', description: 'Will accept null for documents without order.')]
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

    /**
     * @deprecated tag:v6.9.0 - Will be removed. Use getTypeName() instead.
     */
    public function getDocumentType(): ?DocumentTypeEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.9.0.0', 'getTypeName()'),
            silentUntil: 'v6.8.0.0',
        );

        return $this->documentType;
    }

    /**
     * @deprecated tag:v6.9.0 - Will be removed. Use setTypeName() instead.
     */
    public function setDocumentType(DocumentTypeEntity $documentType): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.9.0.0', 'setTypeName()'),
            silentUntil: 'v6.8.0.0',
        );

        $this->documentType = $documentType;
    }

    /**
     * @deprecated tag:v6.9.0 - Will be removed. Use getTypeName() instead.
     */
    public function getDocumentTypeId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.9.0.0', 'getTypeName()'),
            silentUntil: 'v6.8.0.0',
        );

        return $this->documentTypeId;
    }

    /**
     * @deprecated tag:v6.9.0 - Will be removed. Use setTypeName() instead.
     */
    public function setDocumentTypeId(string $documentTypeId): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.9.0.0', 'setTypeName()'),
            silentUntil: 'v6.8.0.0',
        );

        $this->documentTypeId = $documentTypeId;
    }

    public function getTypeName(): ?string
    {
        return $this->typeName;
    }

    public function setTypeName(?string $typeName): void
    {
        $this->typeName = $typeName;
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

    /**
     * @deprecated tag:v6.9.0 - Will be removed. Use getDocumentFiles() instead.
     */
    public function getDocumentMediaFile(): ?MediaEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.9.0.0', 'getDocumentFiles()'),
            silentUntil: 'v6.8.0.0',
        );

        return $this->documentMediaFile;
    }

    /**
     * @deprecated tag:v6.9.0 - Will be removed. Use setDocumentFiles() instead.
     */
    public function setDocumentMediaFile(?MediaEntity $documentMediaFile): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.9.0.0', 'setDocumentFiles()'),
            silentUntil: 'v6.8.0.0',
        );

        $this->documentMediaFile = $documentMediaFile;
    }

    /**
     * @deprecated tag:v6.9.0 - Will be removed. Use getDocumentFiles() instead.
     */
    public function getDocumentMediaFileId(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.9.0.0', 'getDocumentFiles()'),
            silentUntil: 'v6.8.0.0',
        );

        return $this->documentMediaFileId;
    }

    /**
     * @deprecated tag:v6.9.0 - Will be removed. Use setDocumentFiles() instead.
     */
    public function setDocumentMediaFileId(?string $documentMediaFileId): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.9.0.0', 'setDocumentFiles()'),
            silentUntil: 'v6.8.0.0',
        );

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

    /**
     * @deprecated tag:v6.9.0 - Will be removed. Use getDocumentFiles() instead.
     */
    public function getDocumentA11yMediaFileId(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.9.0.0', 'getDocumentFiles()'),
            silentUntil: 'v6.8.0.0',
        );

        return $this->documentA11yMediaFileId;
    }

    /**
     * @deprecated tag:v6.9.0 - Will be removed. Use setDocumentFiles() instead.
     */
    public function setDocumentA11yMediaFileId(?string $mediaFileId): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.9.0.0', 'setDocumentFiles()'),
            silentUntil: 'v6.8.0.0',
        );

        $this->documentA11yMediaFileId = $mediaFileId;
    }

    /**
     * @deprecated tag:v6.9.0 - Will be removed. Use getDocumentFiles() instead.
     */
    public function getDocumentA11yMediaFile(): ?MediaEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.9.0.0', 'getDocumentFiles()'),
            silentUntil: 'v6.8.0.0',
        );

        return $this->documentA11yMediaFile;
    }

    /**
     * @deprecated tag:v6.9.0 - Will be removed. Use setDocumentFiles() instead.
     */
    public function setDocumentA11yMediaFile(?MediaEntity $mediaEntity): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.9.0.0', 'setDocumentFiles()'),
            silentUntil: 'v6.8.0.0',
        );

        $this->documentA11yMediaFile = $mediaEntity;
    }

    /**
     * @internal
     */
    public function getDocumentFiles(): ?DocumentFileCollection
    {
        return $this->documentFiles;
    }

    /**
     * @internal
     */
    public function setDocumentFiles(DocumentFileCollection $documentFiles): void
    {
        $this->documentFiles = $documentFiles;
    }
}
