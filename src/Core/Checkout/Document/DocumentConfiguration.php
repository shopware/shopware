<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Struct\Struct;

class DocumentConfiguration extends Struct
{
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
     * @var string
     */
    protected $pageOrientation;

    /**
     * @var string
     */
    protected $pageSize;

    public function __set($name, $value)
    {
        $this->$name = $value;

        return $this;
    }

    public function __get($name)
    {
        return $this->$name;
    }

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

    public function getApiAlias(): string
    {
        return 'document_configuration';
    }
}
