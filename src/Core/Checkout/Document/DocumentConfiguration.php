<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Country\CountryEntity;

/**
 * @codeCoverageIgnore
 */
#[\AllowDynamicProperties]
#[Package('after-sales')]
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
     * @deprecated tag:v6.7.0 - Will be replaced by single fields: companyStreet, companyZipcode, companyCity, companyCountry
     */
    protected $companyAddress;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $companyName;

    protected string $companyStreet = '';

    protected string $companyZipcode = '';

    protected string $companyCity = '';

    protected string $companyCountryId = '';

    protected ?CountryEntity $companyCountry = null;

    protected string $paymentDueDate = '';

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

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): void
    {
        $this->companyName = $companyName;
    }

    public function getCompanyStreet(): string
    {
        return $this->companyStreet;
    }

    public function setCompanyStreet(string $companyStreet): void
    {
        $this->companyStreet = $companyStreet;
    }

    public function getCompanyZipcode(): string
    {
        return $this->companyZipcode;
    }

    public function setCompanyZipcode(string $companyZipcode): void
    {
        $this->companyZipcode = $companyZipcode;
    }

    public function getCompanyCity(): string
    {
        return $this->companyCity;
    }

    public function setCompanyCity(string $companyCity): void
    {
        $this->companyCity = $companyCity;
    }

    public function getCompanyCountryId(): string
    {
        return $this->companyCountryId;
    }

    public function setCompanyCountryId(string $companyCountryId): void
    {
        $this->companyCountryId = $companyCountryId;
    }

    public function getCompanyCountry(): ?CountryEntity
    {
        return $this->companyCountry;
    }

    public function setCompanyCountry(?CountryEntity $companyCountry): void
    {
        $this->companyCountry = $companyCountry;
    }

    public function getCompanyPhone(): ?string
    {
        return $this->companyPhone;
    }

    public function setCompanyPhone(?string $companyPhone): void
    {
        $this->companyPhone = $companyPhone;
    }

    public function getCompanyEmail(): ?string
    {
        return $this->companyEmail;
    }

    public function setCompanyEmail(?string $companyEmail): void
    {
        $this->companyEmail = $companyEmail;
    }

    public function getCompanyUrl(): ?string
    {
        return $this->companyUrl;
    }

    public function setCompanyUrl(?string $companyUrl): void
    {
        $this->companyUrl = $companyUrl;
    }

    /**
     * @return string[]
     */
    public function getAddressParts(): array
    {
        $parts = [
            $this->getCompanyName() ?? '',
            $this->getCompanyStreet(),
            $this->getCompanyZipcode() . ' ' . $this->getCompanyCity(),
            $this->getCompanyCountry()?->getTranslation('name') ?? '',
        ];

        return array_filter($parts, static fn ($part) => !empty(\trim($part)));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getExecutiveDirector(): ?string
    {
        return $this->executiveDirector;
    }

    public function setExecutiveDirector(?string $executiveDirector): void
    {
        $this->executiveDirector = $executiveDirector;
    }

    public function getPaymentDueDate(): string
    {
        return $this->paymentDueDate;
    }

    public function setPaymentDueDate(string $paymentDueDate): void
    {
        $this->paymentDueDate = $paymentDueDate;
    }

    public function getTaxNumber(): ?string
    {
        return $this->taxNumber;
    }

    public function setTaxNumber(?string $taxNumber): void
    {
        $this->taxNumber = $taxNumber;
    }

    public function getBankName(): ?string
    {
        return $this->bankName;
    }

    public function setBankName(?string $bankName): void
    {
        $this->bankName = $bankName;
    }

    public function getBankIban(): ?string
    {
        return $this->bankIban;
    }

    public function setBankIban(?string $bankIban): void
    {
        $this->bankIban = $bankIban;
    }

    public function getBankBic(): ?string
    {
        return $this->bankBic;
    }

    public function setBankBic(?string $bankBic): void
    {
        $this->bankBic = $bankBic;
    }

    public function getVatId(): ?string
    {
        return $this->vatId;
    }

    public function setVatId(?string $vatId): void
    {
        $this->vatId = $vatId;
    }

    public function getTaxOffice(): ?string
    {
        return $this->taxOffice;
    }

    public function setTaxOffice(?string $taxOffice): void
    {
        $this->taxOffice = $taxOffice;
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
