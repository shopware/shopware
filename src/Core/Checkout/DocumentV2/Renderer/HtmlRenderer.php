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
use Shopware\Core\Checkout\DocumentV2\Twig\TemplateContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Renders the HTML representation of a document via {@see DocumentTemplateRenderer}.
 *
 * Wraps the provider's {@see InvoiceRenderData} in a {@see TemplateContext} together with
 * format-specific overrides (`fileType`, `itemsPerPage`) so the underlying render data stays
 * untouched for any renderer running after this one.
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

        $configuration = new TemplateContext(
            $renderData,
            fileType: self::FORMAT->fileExtension(),
            itemsPerPage: 1000,
        );

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

        $fileStem = $renderData->config->buildFileStem($renderData->documentNumber);

        return new RenderResult(
            self::FORMAT->value,
            $content,
            $fileStem,
            self::FORMAT->fileExtension(),
            self::FORMAT->mimeType(),
        );
    }
}
