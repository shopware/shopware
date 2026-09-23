<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class DocumentBaseConfigSalesChannelEntity extends Entity
{
    use EntityIdTrait;

    protected string $documentBaseConfigId;

    protected ?string $salesChannelId = null;

    protected string $documentTypeId;

    protected ?string $typeName = null;

    protected ?DocumentTypeEntity $documentType = null;

    protected ?DocumentBaseConfigEntity $documentBaseConfig = null;

    protected ?SalesChannelEntity $salesChannel = null;

    public function getDocumentBaseConfigId(): string
    {
        return $this->documentBaseConfigId;
    }

    public function setDocumentBaseConfigId(string $documentBaseConfigId): void
    {
        $this->documentBaseConfigId = $documentBaseConfigId;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
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

    public function getDocumentBaseConfig(): ?DocumentBaseConfigEntity
    {
        return $this->documentBaseConfig;
    }

    public function setDocumentBaseConfig(DocumentBaseConfigEntity $documentBaseConfig): void
    {
        $this->documentBaseConfig = $documentBaseConfig;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
