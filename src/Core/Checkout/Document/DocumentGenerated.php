<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Struct\Struct;

class DocumentGenerated extends Struct
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
    protected $html;

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

    public function setPageOrientation(string $pageOrientation): void
    {
        $this->pageOrientation = $pageOrientation;
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function setHtml(string $html): void
    {
        $this->html = $html;
    }
}
