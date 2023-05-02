<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('customer-order')]
final class RenderedDocument extends Struct
{
    private string $content;

    public function __construct(
        private readonly string $html = '',
        private readonly string $number = '',
        private string $name = '',
        private readonly string $fileExtension = FileTypes::PDF,
        private readonly array $config = [],
        private ?string $contentType = 'application/pdf'
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

    /**
     * @deprecated tag:v6.6.0 - reason:return-type-change - will be changed to void and not return anything anymore
     *
     * @phpstan-ignore-next-line ignore needs to be removed when deprecation is removed
     */
    public function setContent(string $content): string
    {
        return $this->content = $content;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
