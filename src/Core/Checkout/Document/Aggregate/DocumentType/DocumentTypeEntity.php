<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentType;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class DocumentTypeEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $technicalName;

    /**
     * @var ProductTranslationCollection|null
     */
    protected $translations;

    /**
     * @var DocumentCollection|null
     */
    protected $documents;

    /**
     * @var DocumentBaseConfigCollection|null
     */
    protected $documentBaseConfigs;

    /**
     * @var DocumentBaseConfigSalesChannelCollection|null
     */
    protected $documentBaseConfigSalesChannels;

    /**
     * @var array|null
     */
    protected $attributes;

    /**
     * @var \DateTimeInterface
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface
     */
    protected $updatedAt;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getTranslations(): ?ProductTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ProductTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getDocuments(): ?DocumentCollection
    {
        return $this->documents;
    }

    public function setDocuments(DocumentCollection $documents): void
    {
        $this->documents = $documents;
    }

    public function getDocumentBaseConfigs(): ?DocumentBaseConfigCollection
    {
        return $this->documentBaseConfigs;
    }

    public function setDocumentBaseConfigs(?DocumentBaseConfigCollection $documentBaseConfigs): void
    {
        $this->documentBaseConfigs = $documentBaseConfigs;
    }

    public function getDocumentBaseConfigSalesChannels(): ?DocumentBaseConfigSalesChannelCollection
    {
        return $this->documentBaseConfigSalesChannels;
    }

    public function setDocumentBaseConfigSalesChannels(?DocumentBaseConfigSalesChannelCollection $documentBaseConfigSalesChannels): void
    {
        $this->documentBaseConfigSalesChannels = $documentBaseConfigSalesChannels;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
