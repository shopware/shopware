<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
class DocumentBaseConfigEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $name;

    protected ?string $filenamePrefix = null;

    protected ?string $filenameSuffix = null;

    protected string $documentNumber;

    protected bool $global = false;

    protected ?string $documentTypeId = null;

    protected ?string $logoId = null;

    /**
     * @var array<string, string|bool|array<int, string>>|null
     */
    protected ?array $config = null;

    /**
     * @internal
     */
    protected ?string $pageSize = null;

    /**
     * @internal
     */
    protected ?string $pageOrientation = null;

    /**
     * @internal
     */
    protected ?int $itemsPerPage = null;

    /**
     * @internal
     */
    protected ?bool $displayHeader = null;

    /**
     * @internal
     */
    protected ?bool $displayFooter = null;

    /**
     * @internal
     */
    protected ?bool $displayPageCount = null;

    /**
     * @internal
     */
    protected ?bool $displayCompanyAddress = null;

    /**
     * @internal
     */
    protected ?bool $displayReturnAddress = null;

    /**
     * @internal
     */
    protected ?bool $displayCustomerVatId = null;

    protected ?DocumentBaseConfigSalesChannelCollection $salesChannels = null;

    protected ?DocumentTypeEntity $documentType = null;

    protected ?MediaEntity $logo = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSalesChannels(): ?DocumentBaseConfigSalesChannelCollection
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

    /**
     * @internal
     */
    public function getPageSize(): ?string
    {
        return $this->pageSize;
    }

    /**
     * @internal
     */
    public function setPageSize(?string $pageSize): void
    {
        $this->pageSize = $pageSize;
    }

    /**
     * @internal
     */
    public function getPageOrientation(): ?string
    {
        return $this->pageOrientation;
    }

    /**
     * @internal
     */
    public function setPageOrientation(?string $pageOrientation): void
    {
        $this->pageOrientation = $pageOrientation;
    }

    /**
     * @internal
     */
    public function getItemsPerPage(): ?int
    {
        return $this->itemsPerPage;
    }

    /**
     * @internal
     */
    public function setItemsPerPage(?int $itemsPerPage): void
    {
        $this->itemsPerPage = $itemsPerPage;
    }

    /**
     * @internal
     */
    public function getDisplayHeader(): ?bool
    {
        return $this->displayHeader;
    }

    /**
     * @internal
     */
    public function setDisplayHeader(?bool $displayHeader): void
    {
        $this->displayHeader = $displayHeader;
    }

    /**
     * @internal
     */
    public function getDisplayFooter(): ?bool
    {
        return $this->displayFooter;
    }

    /**
     * @internal
     */
    public function setDisplayFooter(?bool $displayFooter): void
    {
        $this->displayFooter = $displayFooter;
    }

    /**
     * @internal
     */
    public function getDisplayPageCount(): ?bool
    {
        return $this->displayPageCount;
    }

    /**
     * @internal
     */
    public function setDisplayPageCount(?bool $displayPageCount): void
    {
        $this->displayPageCount = $displayPageCount;
    }

    /**
     * @internal
     */
    public function getDisplayCompanyAddress(): ?bool
    {
        return $this->displayCompanyAddress;
    }

    /**
     * @internal
     */
    public function setDisplayCompanyAddress(?bool $displayCompanyAddress): void
    {
        $this->displayCompanyAddress = $displayCompanyAddress;
    }

    /**
     * @internal
     */
    public function getDisplayReturnAddress(): ?bool
    {
        return $this->displayReturnAddress;
    }

    /**
     * @internal
     */
    public function setDisplayReturnAddress(?bool $displayReturnAddress): void
    {
        $this->displayReturnAddress = $displayReturnAddress;
    }

    /**
     * @internal
     */
    public function getDisplayCustomerVatId(): ?bool
    {
        return $this->displayCustomerVatId;
    }

    /**
     * @internal
     */
    public function setDisplayCustomerVatId(?bool $displayCustomerVatId): void
    {
        $this->displayCustomerVatId = $displayCustomerVatId;
    }
}
