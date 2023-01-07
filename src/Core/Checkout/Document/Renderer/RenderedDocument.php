<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @package customer-order
 */
final class RenderedDocument extends Struct
{
    private string $number;

    private string $html;

    private string $name;

    private string $content;

    private string $fileExtension;

    private ?string $contentType;

    private array $config;

    public function __construct(
        string $html = '',
        string $number = '',
        string $name = '',
        string $fileExtension = FileTypes::PDF,
        array $config = [],
        ?string $contentType = 'application/pdf'
    ) {
        $this->html = $html;
        $this->number = $number;
        $this->name = $name;
        $this->fileExtension = $fileExtension;
        $this->contentType = $contentType;
        $this->config = $config;
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

    public function setContent(string $content): string
    {
        return $this->content = $content;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
