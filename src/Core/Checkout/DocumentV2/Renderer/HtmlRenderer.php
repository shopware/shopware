<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\DocumentV2\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\DocumentV2\Twig\PaginationCounter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Renders the HTML representation of a document via {@see DocumentTemplateRenderer}.
 *
 * The provider's {@see InvoiceRenderData} is cloned before applying format-specific overrides
 * (`fileType`, `itemsPerPage`) so renderers running after this one see the original configuration.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class HtmlRenderer extends AbstractDocumentRenderer
{
    final public const FORMAT = DocumentFormat::HTML;

    public function __construct(
        private DocumentTemplateRenderer $documentTemplateRenderer,
    ) {
    }

    public function getFormat(): string
    {
        return self::FORMAT->value;
    }

    public function getDocumentTypes(): array
    {
        return [
            DocumentType::INVOICE->value,
        ];
    }

    public function renderToString(RenderInput $input, RenderState $state, Context $context): RenderResult
    {
        $renderData = $input->requireData(
            InvoiceDataProvider::KEY,
            InvoiceRenderData::class
        );

        $configuration = clone $renderData->configuration;
        $configuration->merge([
            'fileType' => self::FORMAT->fileExtension(),
            'itemsPerPage' => 1000,
        ]);

        $template = DocumentType::from($input->documentType)->templatePath();

        $content = $this->documentTemplateRenderer->render(
            $template,
            $input,
            $context,
            [
                'config' => $configuration,
                'counter' => new PaginationCounter(),
            ],
        );

        return new RenderResult(
            self::FORMAT->value,
            $content,
            $configuration->buildName(),
            self::FORMAT->fileExtension(),
            self::FORMAT->mimeType(),
        );
    }
}
