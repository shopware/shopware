<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentType;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class DocumentTypeEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $technicalName;

    /**
     * @var ProductTranslationCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $translations;

    /**
     * @var DocumentCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $documents;

    /**
     * @var DocumentBaseConfigCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $documentBaseConfigs;

    /**
     * @var DocumentBaseConfigSalesChannelCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $documentBaseConfigSalesChannels;

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

    public function setDocumentBaseConfigs(DocumentBaseConfigCollection $documentBaseConfigs): void
    {
        $this->documentBaseConfigs = $documentBaseConfigs;
    }

    public function getDocumentBaseConfigSalesChannels(): ?DocumentBaseConfigSalesChannelCollection
    {
        return $this->documentBaseConfigSalesChannels;
    }

    public function setDocumentBaseConfigSalesChannels(DocumentBaseConfigSalesChannelCollection $documentBaseConfigSalesChannels): void
    {
        $this->documentBaseConfigSalesChannels = $documentBaseConfigSalesChannels;
    }
}
