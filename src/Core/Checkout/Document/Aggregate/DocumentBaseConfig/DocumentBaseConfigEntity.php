<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class DocumentBaseConfigEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $fileNamePrefix;

    /**
     * @var string|null
     */
    protected $fileNameSuffix;

    /**
     * @var string
     */
    protected $documentNumber;

    /**
     * @var bool
     */
    protected $global = false;

    /**
     * @var string|null
     */
    protected $typeId;

    /**
     * @var string[]|null
     */
    protected $config;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var DocumentBaseConfigSalesChannelCollection
     */
    protected $salesChannels;

    /**
     * @var DocumentTypeEntity|null
     */
    protected $type;

    /**
     * @var MediaEntity|null
     */
    protected $logo;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getSalesChannels(): DocumentBaseConfigSalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(DocumentBaseConfigSalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getTypeId(): ?string
    {
        return $this->typeId;
    }

    public function setTypeId(?string $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function setDocumentNumber(string $documentNumber): void
    {
        $this->documentNumber = $documentNumber;
    }

    public function getType(): ?DocumentTypeEntity
    {
        return $this->type;
    }

    public function setType(?DocumentTypeEntity $type): void
    {
        $this->type = $type;
    }

    public function getLogo(): ?MediaEntity
    {
        return $this->logo;
    }

    public function setLogo(?MediaEntity $logo): void
    {
        $this->logo = $logo;
    }

    /**
     * @return string[]|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * @param string[]|null $config
     */
    public function setConfig(?array $config): void
    {
        $this->config = $config;
    }

    public function getFileNamePrefix(): ?string
    {
        return $this->fileNamePrefix;
    }

    public function setFileNamePrefix(?string $fileNamePrefix): void
    {
        $this->fileNamePrefix = $fileNamePrefix;
    }

    public function getFileNameSuffix(): ?string
    {
        return $this->fileNameSuffix;
    }

    public function setFileNameSuffix(?string $fileNameSuffix): void
    {
        $this->fileNameSuffix = $fileNameSuffix;
    }
}
