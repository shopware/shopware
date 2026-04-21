<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * Represents one rendered format output before it is persisted to media storage.
 *
 * It contains both the binary or textual content and the metadata needed by the renderer to
 * create the final persisted file artifact.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class RenderResult
{
    public function __construct(
        private string $format,
        private string $content,
        private string $fileName,
        private string $fileExtension,
        private string $mimeType,
    ) {
    }

    public function getFormat(): string
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
