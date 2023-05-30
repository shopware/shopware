<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[\AllowDynamicProperties]
#[Package('customer-order')]
class DocumentConfiguration extends Struct
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var array<string>
     */
    protected $deliveryCountries;

    /**
     * @var bool|null
     */
    protected $displayPrices;

    /**
     * @var array|null
     */
    protected $logo;

    /**
     * @var string|null
     */
    protected $filenamePrefix;

    /**
     * @var string|null
     */
    protected $filenameSuffix;

    /**
     * @var string|null
     */
    protected $documentNumber;

    /**
     * @var string|null
     */
    protected $documentDate;

    /**
     * @var string|null
     */
    protected $documentComment;

    /**
     * @var string|null
     */
    protected $pageOrientation;

    /**
     * @var string|null
     */
    protected $pageSize;

    /**
     * @var bool|null
     */
    protected $displayFooter;

    /**
     * @var bool|null
     */
    protected $displayHeader;

    /**
     * @var bool|null
     */
    protected $displayLineItems;

    /**
     * @var bool|null
     */
    protected $displayLineItemPosition;

    /**
     * @var int|null
     */
    protected $itemsPerPage;

    /**
     * @var bool|null
     */
    protected $displayPageCount;

    /**
     * @var bool|null
     */
    protected $displayCompanyAddress;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var string|null
     */
    protected $companyAddress;

    /**
     * @var string|null
     */
    protected $companyName;

    /**
     * @var string|null
     */
    protected $companyEmail;

    /**
     * @var string|null
     */
    protected $companyPhone;

    /**
     * @var string|null
     */
    protected $companyUrl;

    /**
     * @var string|null
     */
    protected $taxNumber;

    /**
     * @var string|null
     */
    protected $taxOffice;

    /**
     * @var string|null
     */
    protected $vatId;

    /**
     * @var string|null
     */
    protected $bankName;

    /**
     * @var string|null
     */
    protected $bankIban;

    /**
     * @var string|null
     */
    protected $bankBic;

    /**
     * @var string|null
     */
    protected $placeOfJurisdiction;

    /**
     * @var string|null
     */
    protected $placeOfFulfillment;

    /**
     * @var string|null
     */
    protected $executiveDirector;

    /**
     * @var array
     */
    protected $custom = [];

    /**
     * @var bool
     */
    protected $diplayLineItemPosition;

    /**
     * @var bool
     */
    protected $displayInCustomerAccount;

    /**
     * @var string
     */
    protected $documentTypeId;

    /**
     * @param string                     $name
     * @param array|bool|int|string|null $value
     *
     * @return $this
     */
    public function __set($name, $value)
    {
        $this->$name = $value; /* @phpstan-ignore-line */

        return $this;
    }

    /**
     * @param string $name
     *
     * @return array|bool|int|string|null
     */
    public function __get($name)
    {
        return $this->$name; /* @phpstan-ignore-line */
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
