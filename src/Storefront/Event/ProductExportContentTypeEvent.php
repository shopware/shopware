<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ProductExportContentTypeEvent extends Event
{
    /** @var string */
    private $fileFormat;

    /** @var string */
    private $contentType;

    public function __construct(string $fileFormat, string $contentType)
    {
        $this->fileFormat = $fileFormat;
        $this->contentType = $contentType;
    }

    public function getFileFormat(): string
    {
        return $this->fileFormat;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }
}
