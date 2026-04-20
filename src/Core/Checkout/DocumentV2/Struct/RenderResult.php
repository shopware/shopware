<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class RenderResult
{
    public function __construct(
        private DocumentFormat $format,
        private string $content,
        private string $fileName,
        private string $fileExtension,
        private string $mimeType,
    ) {
    }

    public function getFileNameWithExtension(): string
    {
        return $this->fileName . '.' . $this->fileExtension;
    }

    public function getFormat(): DocumentFormat
    {
        return $this->format;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }
}
