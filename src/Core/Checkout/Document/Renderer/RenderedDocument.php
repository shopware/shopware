<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
final class RenderedDocument extends Struct
{
    /**
     * @param array<string, mixed> $config
     *
     * @deprecated tag:v6.7.0 - reason:parameter-change - html argument will be removed
     */
    public function __construct(
        private readonly string $html = '',
        private readonly string $number = '',
        private string $name = '',
        private readonly string $fileExtension = FileTypes::PDF,
        private readonly array $config = [],
        private ?string $contentType = 'application/pdf',
        private string $content = ''
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

    /**
     * @deprecated tag:v6.7.0 - will be removed - use content property for the rendered value instead
     */
    public function getHtml(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Property and method will be removed. Use `content` property for the rendered value');

        return $this->html;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getContentType(): string
    {
        return $this->contentType ?? 'application/pdf';
    }

    public function setContentType(?string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function getFileExtension(): string
    {
        return $this->fileExtension;
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
}
