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
use Shopware\Core\Checkout\DocumentV2\Xml\XmlFormatter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Renders a document into XRechnung 3.0 (CII) XML.
 *
 * Data + math live in {@see InvoiceDataProvider}; the same DTO feeds the HTML and Zugferd
 * renderers. After Twig produces the raw markup it is piped through {@see XmlFormatter} for
 * deterministic pretty-printing + well-formedness validation.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class XmlRenderer extends AbstractDocumentRenderer
{
    final public const FORMAT = DocumentFormat::ZUGFERD_XML;

    public function __construct(
        private DocumentTemplateRenderer $documentTemplateRenderer,
        private XmlFormatter $xmlFormatter,
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
            InvoiceRenderData::class,
        );

        $template = $renderData->templatePathFor(self::FORMAT->value);

        $raw = $this->documentTemplateRenderer->render(
            $template,
            $input,
            $context,
            ['renderData' => $renderData],
        );

        $content = $this->xmlFormatter->format($raw);

        return new RenderResult(
            format: self::FORMAT->value,
            content: $content,
            fileName: $renderData->config->buildFileStem($renderData->documentNumber),
            fileExtension: self::FORMAT->fileExtension(),
            mimeType: self::FORMAT->mimeType(),
        );
    }
}
