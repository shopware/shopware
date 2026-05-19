<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Com\Tecnick\Pdf\Tcpdf;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Renders a document into PDF by consuming the {@see HtmlRenderer} output from {@see RenderState}
 * and running it through Dompdf.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class PdfRenderer extends AbstractDocumentRenderer
{
    final public const FORMAT = DocumentFormat::PDF;

    private const TCPDF_FONT_PATH_KEY = 'K_PATH_FONTS';

    private const TCPDF_FONT_VENDOR_PATH = '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts/';

    public function __construct(
        private string $projectDir,
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

    public function getDependencies(): array
    {
        return [
            DocumentFormat::HTML->value,
        ];
    }

    public function renderToString(RenderInput $input, RenderState $state, Context $context): RenderResult
    {
        $html = $state->require(DocumentFormat::HTML->value)->content;

        // POC: the embedded <style> blocks (style_base_portrait.css.twig etc.) use CSS features
        // tc-lib-pdf does not understand (rgba with whitespace, width: auto, margin: auto, ...).
        // Strip them; tag-level inline styles in the body still come through.
        $html = (string) \preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);

        $renderData = $input->requireData(
            InvoiceDataProvider::KEY,
            InvoiceRenderData::class,
        );

        $config = $renderData->config;

        // tc-lib-pdf-font reads glyph data from this directory; defining as global constant is the
        // library's documented bootstrap step.
        if (!\defined(self::TCPDF_FONT_PATH_KEY)) {
            \define(self::TCPDF_FONT_PATH_KEY, $this->projectDir . self::TCPDF_FONT_VENDOR_PATH);
        }

        $pdf = new Tcpdf(
            'mm',
            true,
            false,
            true,
            'pdfua',
            null
        );

        $pdf->setCreator('Shopware');
        $pdf->setTitle($renderData->documentNumber);
        $pdf->setPDFFilename($renderData->documentNumber . '.pdf');
        $pdf->setLanguage('en-US');
        $pdf->enableDefaultPageContent();

        $font = $pdf->font->insert(
            $pdf->pon,
            'dejavusans',
            '',
            10
        );

        $pdf->addPage();
        $pdf->page->addContent($font['out']);

        $pdf->addHTMLCell(
            $html,
            15.0,
            15.0,
            180.0
        );

        return new RenderResult(
            format: self::FORMAT->value,
            content: $pdf->getOutPDFString(),
            fileName: $config->buildFileStem($renderData->documentNumber),
            fileExtension: self::FORMAT->fileExtension(),
            mimeType: self::FORMAT->mimeType(),
        );
    }
}
