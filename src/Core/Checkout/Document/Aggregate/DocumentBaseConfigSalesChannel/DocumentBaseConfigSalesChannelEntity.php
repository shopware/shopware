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

    /**
     * @deprecated tag:v6.8.0 reason:remove-duplicate-field - Will be removed. Use the typeName property of the documentBaseConfig instead.
     */
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
     * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed. Use getDocumentBaseConfig()?->getTypeName() instead.
     */
    public function getDocumentTypeId(): string
    {
        return $this->documentTypeId;
    }

    /**
     * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed. Use getDocumentBaseConfig()?->setTypeName() instead.
     */
    public function setDocumentTypeId(string $documentTypeId): void
    {
        $this->documentTypeId = $documentTypeId;
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-duplicate-field - Will be removed. Use getDocumentBaseConfig()?->getTypeName() instead.
     */
    public function getTypeName(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, 'getTypeName', 'v6.8.0.0', self::class . '::getDocumentBaseConfig()::getTypeName()'));

        return $this->typeName;
    }

    /**
     * @deprecated tag:v6.8.0 reason:remove-duplicate-field - Will be removed. Use getDocumentBaseConfig()?->setTypeName() instead.
     */
    public function setTypeName(?string $typeName): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, 'setTypeName', 'v6.8.0.0', self::class . '::getDocumentBaseConfig()::setTypeName()'));

        $this->typeName = $typeName;
    }

    /**
     * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed. Use getDocumentBaseConfig()?->getTypeName() instead.
     */
    public function getDocumentType(): ?DocumentTypeEntity
    {
        return $this->documentType;
    }

    /**
     * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed. Use getDocumentBaseConfig()?->setTypeName() instead.
     */
    public function setDocumentType(DocumentTypeEntity $documentType): void
    {
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
