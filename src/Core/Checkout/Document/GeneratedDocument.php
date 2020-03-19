<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Struct\Struct;

class GeneratedDocument extends Struct
{
    /**
     * @var string
     */
    protected $filename;

    /**
     * @var string
     */
    protected $fileBlob;

    /**
     * @var string
     */
    protected $pageOrientation = 'portrait';

    /**
     * @var string
     */
    protected $pageSize = 'a4';

    /**
     * @var string
     */
    protected $html;

    /**
     * @var string
     */
    protected $contentType;

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getFileBlob(): string
    {
        return $this->fileBlob;
    }

    public function setFileBlob(string $fileBlob): void
    {
        $this->fileBlob = $fileBlob;
    }

    public function getPageOrientation(): string
    {
        return $this->pageOrientation;
    }

    public function setPageOrientation(?string $pageOrientation): void
    {
        if ($pageOrientation !== null) {
            $this->pageOrientation = $pageOrientation;
        }
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function setHtml(string $html): void
    {
        $this->html = $html;
    }

    public function getPageSize(): string
    {
        return $this->pageSize;
    }

    /**
     * @param string $pageSize
     */
    public function setPageSize(?string $pageSize): void
    {
        if ($pageSize !== null) {
            $this->pageSize = $pageSize;
        }
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function getApiAlias(): string
    {
        return 'document_generated';
    }
}
