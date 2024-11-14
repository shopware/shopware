<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class DocumentBaseConfigEntity extends Entity
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
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $filenamePrefix;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $filenameSuffix;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $documentNumber;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $global = false;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $documentTypeId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $logoId;

    /**
     * @var array<string, string|bool|array<int, string>>|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $config;

    /**
     * @var DocumentBaseConfigSalesChannelCollection
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salesChannels;

    /**
     * @var DocumentTypeEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $documentType;

    /**
     * @var MediaEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
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

    public function getSalesChannels(): DocumentBaseConfigSalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(DocumentBaseConfigSalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getDocumentTypeId(): ?string
    {
        return $this->documentTypeId;
    }

    public function setDocumentTypeId(?string $documentTypeId): void
    {
        $this->documentTypeId = $documentTypeId;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function setDocumentNumber(string $documentNumber): void
    {
        $this->documentNumber = $documentNumber;
    }

    public function isGlobal(): bool
    {
        return $this->global;
    }

    public function setGlobal(bool $global): void
    {
        $this->global = $global;
    }

    public function getDocumentType(): ?DocumentTypeEntity
    {
        return $this->documentType;
    }

    public function setDocumentType(DocumentTypeEntity $documentType): void
    {
        $this->documentType = $documentType;
    }

    public function getLogoId(): ?string
    {
        return $this->logoId;
    }

    public function setLogoId(string $logoId): void
    {
        $this->logoId = $logoId;
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
     * @return array<string, string|bool|array<int, string>>|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * @param array<string, string|bool|array<int, string>>|null $config
     */
    public function setConfig(?array $config): void
    {
        $this->config = $config;
    }

    public function getFilenamePrefix(): ?string
    {
        return $this->filenamePrefix;
    }

    public function setFilenamePrefix(?string $filenamePrefix): void
    {
        $this->filenamePrefix = $filenamePrefix;
    }

    public function getFilenameSuffix(): ?string
    {
        return $this->filenameSuffix;
    }

    public function setFilenameSuffix(?string $filenameSuffix): void
    {
        $this->filenameSuffix = $filenameSuffix;
    }
}
