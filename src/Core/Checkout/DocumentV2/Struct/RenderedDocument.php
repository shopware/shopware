<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Deprecation\BCChange\ClassMoved;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('after-sales')]
#[ClassMoved(version: 'v6.9.0', previousClassName: 'Shopware\Core\Checkout\Document\Renderer\RenderedDocument')]
class RenderedDocument extends Struct
{
    /**
     * Mirrors DocumentFormat::PDF; constructor defaults must be constant expressions, so the enum cannot be called here.
     */
    private const FILE_EXTENSION = 'pdf';

    private const FILE_CONTENT_TYPE = 'application/pdf';

    private string $template = '';

    private ?OrderEntity $order = null;

    private ?Context $context = null;

    /**
     * @var array<string, mixed>
     */
    private array $parameters = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly string $number = '',
        private string $name = '',
        private string $fileExtension = self::FILE_EXTENSION,
        private readonly array $config = [],
        private ?string $contentType = self::FILE_CONTENT_TYPE,
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

    public function getContent(): string
    {
        return $this->content;
    }

    public function getContentType(): string
    {
        return $this->contentType ?? self::FILE_CONTENT_TYPE;
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

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getContext(): ?Context
    {
        return $this->context;
    }

    public function setContext(?Context $context): void
    {
        $this->context = $context;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function addParameter(string $key, mixed $value): void
    {
        $this->parameters[$key] = $value;
    }
}
