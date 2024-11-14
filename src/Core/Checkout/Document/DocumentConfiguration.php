<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[\AllowDynamicProperties]
#[Package('checkout')]
class DocumentConfiguration extends Struct
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $id;

    /**
     * @var array<string>
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $deliveryCountries;

    /**
     * @var bool|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $displayPrices;

    /**
     * @var array<string, mixed>|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $logo;

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
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $documentNumber;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $documentDate;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $documentComment;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $pageOrientation;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $pageSize;

    /**
     * @var bool|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $displayFooter;

    /**
     * @var bool|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $displayHeader;

    /**
     * @var bool|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $displayLineItems;

    /**
     * @var bool|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $displayLineItemPosition;

    /**
     * @var int|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $itemsPerPage;

    /**
     * @var bool|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $displayPageCount;

    /**
     * @var bool|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $displayCompanyAddress;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $title;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $companyAddress;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $companyName;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $companyEmail;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $companyPhone;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $companyUrl;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $taxNumber;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $taxOffice;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $vatId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $bankName;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $bankIban;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $bankBic;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $placeOfJurisdiction;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $placeOfFulfillment;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $executiveDirector;

    /**
     * @var array<string, mixed>
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $custom = [];

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $diplayLineItemPosition;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $displayInCustomerAccount;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $documentTypeId;

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
}
