<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('inventory')]
class ProductExportContentTypeEvent extends Event
{
    private string $fileFormat;

    private string $contentType;

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
