<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
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
 * Consumes the shared meta. The raw Twig markup is then piped through
 * {@see XmlFormatter} for deterministic pretty-printing and well-formedness validation.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class ZugferdXmlRenderer extends AbstractDocumentRenderer
{
    final public const FORMAT = DocumentFormat::ZUGFERD_XML;

    private const TEMPLATE_PATTERN = '@Framework/documents/zugferd/%s.xml.twig';

    public function __construct(
        private DocumentTemplateRenderer $documentTemplateRenderer,
        private XmlFormatter $xmlFormatter,
    ) {
    }

    public function getFormat(): string
    {
        return self::FORMAT->value;
    }

    public function getFileExtension(): string
    {
        return self::FORMAT->fileExtension();
    }

    public function renderToString(RenderInput $input, RenderState $state, Context $context): RenderResult
    {
        $meta = $input->requireData(
            DocumentMetaProvider::KEY,
            DocumentMetaRenderData::class,
        );

        // app document types legitimately have no typed render-data provider and ship their own
        // template, so render data is only optional for those
        $renderData = DocumentType::tryFrom($input->documentType) !== null
            ? $input->requireData($input->documentType, AbstractRenderData::class)
            : $input->getData($input->documentType);

        $template = \sprintf(self::TEMPLATE_PATTERN, $input->documentType);

        $raw = $this->documentTemplateRenderer->render(
            $template,
            $input,
            $context,
            [
                'meta' => $meta,
                'renderData' => $renderData,
            ],
        );

        $content = $this->xmlFormatter->format($raw);
        $fileStem = $meta->config->buildFileStem($meta->documentNumber, self::FORMAT->value);

        return new RenderResult(
            format: self::FORMAT->value,
            content: $content,
            fileName: $fileStem,
            fileExtension: self::FORMAT->fileExtension(),
            mimeType: self::FORMAT->mimeType(),
        );
    }
}
