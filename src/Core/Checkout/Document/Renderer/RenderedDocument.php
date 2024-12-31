<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
final class RenderedDocument extends Struct
{
    final public const PDF_CONTENT_TYPE = 'application/pdf';

    final public const HTML_CONTENT_TYPE = 'text/html';

    private string $content;

    private ?RenderedDocument $htmlA11y = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly string $html = '',
        private readonly string $number = '',
        private string $name = '',
        private string $fileExtension = FileTypes::PDF,
        private readonly array $config = [],
        private ?string $contentType = self::PDF_CONTENT_TYPE,
    ) {
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getContentType(): string
    {
        return $this->contentType ?? self::PDF_CONTENT_TYPE;
    }

    public function setContentType(?string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }

    public function setFileExtension(string $fileExtension): void
    {
        $this->fileExtension = $fileExtension;
    }

    public function getPageOrientation(): string
    {
        return $this->config['pageOrientation'] ?? 'portrait';
    }

    public function getPageSize(): string
    {
        return $this->config['pageSize'] ?? 'a4';
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function getHtmlA11y(): ?RenderedDocument
    {
        return $this->htmlA11y;
    }

    public function setHtmlA11y(?RenderedDocument $htmlA11y): void
    {
        $this->htmlA11y = $htmlA11y;
    }
}
