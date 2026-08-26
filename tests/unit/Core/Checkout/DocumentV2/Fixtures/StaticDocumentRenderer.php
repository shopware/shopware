<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class StaticDocumentRenderer extends AbstractDocumentRenderer
{
    /**
     * @param list<string> $dependencies
     */
    public function __construct(
        private DocumentFormat|string $format = DocumentFormat::PDF,
        private array $dependencies = [],
        private ?string $fileExtension = null,
        private ?string $mimeType = null,
    ) {
    }

    public function getFormat(): string
    {
        return $this->format instanceof DocumentFormat ? $this->format->value : $this->format;
    }

    public function getFileExtension(): string
    {
        return $this->fileExtension ?? ($this->format instanceof DocumentFormat ? $this->format->fileExtension() : $this->format);
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function renderToString(RenderInput $input, RenderState $state, Context $context): RenderResult
    {
        return new RenderResult(
            $this->getFormat(),
            'content',
            'filename',
            $this->getFileExtension(),
            $this->mimeType ?? ($this->format instanceof DocumentFormat ? $this->format->mimeType() : 'application/octet-stream'),
        );
    }
}
