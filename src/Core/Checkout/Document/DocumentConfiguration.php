<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[\AllowDynamicProperties]
#[Package('after-sales')]
class DocumentConfiguration extends Struct
{
    protected string $id;

    /**
     * @var array<string>
     */
    protected array $deliveryCountries = [];

    protected ?bool $displayPrices = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $logo = null;

    protected ?string $filenamePrefix = null;

    protected ?string $filenameSuffix = null;

    protected ?string $documentNumber = null;

    protected ?string $documentDate = null;

    protected ?string $documentComment = null;

    protected ?string $pageOrientation = null;

    protected ?string $pageSize = null;

    protected ?bool $displayFooter = null;

    protected ?bool $displayHeader = null;

    protected ?bool $displayLineItems = null;

    protected ?bool $displayLineItemPosition = null;

    protected ?int $itemsPerPage = null;

    protected ?bool $displayPageCount = null;

    protected ?bool $displayCompanyAddress = null;

    protected ?string $title = null;

    protected ?string $companyAddress = null;

    protected ?string $companyName = null;

    protected ?string $companyEmail = null;

    protected ?string $companyPhone = null;

    protected ?string $companyUrl = null;

    protected ?string $taxNumber = null;

    protected ?string $taxOffice = null;

    protected ?string $vatId = null;

    protected ?string $bankName = null;

    protected ?string $bankIban = null;

    protected ?string $bankBic = null;

    protected ?string $placeOfJurisdiction = null;

    protected ?string $placeOfFulfillment = null;

    protected ?string $executiveDirector = null;

    /**
     * @var array<string, mixed>
     */
    protected array $custom = [];

    protected bool $diplayLineItemPosition;

    protected bool $displayInCustomerAccount;

    protected string $documentTypeId;

    /**
     * @var array<string>
     */
    protected array $fileTypes = [];

    /**
     * @param string $name
     * @param array<array-key, mixed>|bool|int|string|null $value
     */
    public function __set($name, $value): void
    {
        $this->$name = $value;
    }

    /**
     * @param string $name
     *
     * @return array<array-key, mixed>|bool|int|string|null
     */
    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return property_exists($this, $name);
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

    public function getDocumentNumber(): ?string
    {
        return $this->documentNumber;
    }

    public function setDocumentNumber(?string $documentNumber): void
    {
        $this->documentNumber = $documentNumber;
    }

    public function getDocumentComment(): ?string
    {
        return $this->documentComment;
    }

    public function getDocumentDate(): ?string
    {
        return $this->documentDate;
    }

    public function setDocumentComment(?string $documentComment): void
    {
        $this->documentComment = $documentComment;
    }

    public function getPageOrientation(): ?string
    {
        return $this->pageOrientation;
    }

    public function setPageOrientation(?string $pageOrientation): void
    {
        $this->pageOrientation = $pageOrientation;
    }

    public function getPageSize(): ?string
    {
        return $this->pageSize;
    }

    public function setPageSize(?string $pageSize): void
    {
        $this->pageSize = $pageSize;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function merge(array $config): self
    {
        return DocumentConfigurationFactory::mergeConfiguration($this, $config);
    }

    public function buildName(): string
    {
        return $this->filenamePrefix . $this->documentNumber . $this->filenameSuffix;
    }

    public function getApiAlias(): string
    {
        return 'document_configuration';
    }

    /**
     * @return array<string>
     */
    public function getFileTypes(): array
    {
        return $this->fileTypes;
    }

    /**
     * @param array<string> $types
     */
    public function setFileTypes(array $types): void
    {
        $this->fileTypes = $types;
    }
}
