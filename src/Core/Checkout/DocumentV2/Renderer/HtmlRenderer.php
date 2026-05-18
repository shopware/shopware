<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\DocumentV2\Template\DocumentTemplateRenderer;
use Shopware\Core\Checkout\DocumentV2\Template\PaginationCounter;
use Shopware\Core\Checkout\DocumentV2\Template\TemplateContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Renders the HTML representation of a document via {@see DocumentTemplateRenderer}.
 *
 * Output doubles as input for {@see PdfRenderer}, so the render mode is gated on
 * {@see RenderInput::$preview}:
 *  - `preview=true` keeps the legacy html-preview overrides (extra CSS via
 *    `style_base_html.css.twig`, `itemsPerPage=1000`) used by the browser-facing
 *    HTML document file.
 *  - `preview=false` produces print-styled HTML with the configured `itemsPerPage`
 *    so Dompdf can paginate naturally.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class HtmlRenderer extends AbstractDocumentRenderer
{
    final public const FORMAT = DocumentFormat::HTML;

    private const PREVIEW_ITEMS_PER_PAGE = 1000;

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

        // Preview is one scrollable HTML page in the browser, so we collapse the per-page item
        // limit onto a single logical page; print mode keeps the configured value so Dompdf
        // can emit CSS page-break markers and paginate naturally.
        $itemsPerPage = $input->preview ? self::PREVIEW_ITEMS_PER_PAGE : $renderData->config->itemsPerPage;

        $configuration = new TemplateContext(
            $renderData,
            preview: $input->preview,
            itemsPerPage: $itemsPerPage,
        );

        $template = $renderData->templatePathFor(self::FORMAT->value);

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
