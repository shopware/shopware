<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
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
     * @param list<string> $documentTypes
     * @param list<string> $dependencies
     */
    public function __construct(
        private DocumentFormat $format = DocumentFormat::PDF,
        private array $documentTypes = [DocumentType::INVOICE->value],
        private array $dependencies = [],
    ) {
    }

    public function getDocumentTypes(): array
    {
        return $this->documentTypes;
    }

    public function getFormat(): string
    {
        return $this->format->value;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function renderToString(RenderInput $input, RenderState $state, Context $context): RenderResult
    {
        return new RenderResult(
            $this->format->value,
            'content',
            'filename',
            $this->format->fileExtension(),
            $this->format->mimeType(),
        );
    }
}
