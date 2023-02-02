<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('customer-order')]
class DocumentBaseConfigSalesChannelEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $documentBaseConfigId;

    /**
     * @var string|null
     */
    protected $salesChannelId;

    /**
     * @var string
     */
    protected $documentTypeId;

    /**
     * @var DocumentTypeEntity|null
     */
    protected $documentType;

    /**
     * @var DocumentBaseConfigEntity|null
     */
    protected $documentBaseConfig;

    /**
     * @var SalesChannelEntity|null
     */
    protected $salesChannel;

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

    /**
     * @param string $salesChannelId
     */
    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getDocumentTypeId(): string
    {
        return $this->documentTypeId;
    }

    public function setDocumentTypeId(string $documentTypeId): void
    {
        $this->documentTypeId = $documentTypeId;
    }

    public function getDocumentType(): ?DocumentTypeEntity
    {
        return $this->documentType;
    }

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
