<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use horstoeko\zugferd\ZugferdDocumentPdfMerger;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Merges the rendered PDF and Zugferd XML into a single PDF/A-3 with the XML embedded as a
 * Factur-X attachment, via {@see ZugferdDocumentPdfMerger} (which auto-detects the XRechnung
 * profile from the XML). Depends on the {@see PdfRenderer} and {@see ZugferdXmlRenderer} outputs.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class ZugferdEmbeddedPdfRenderer extends AbstractDocumentRenderer
{
    final public const FORMAT = DocumentFormat::ZUGFERD_EMBEDDED_PDF;

    private const CREATOR_PREFIX = 'Shopware@';

    public function __construct(
        private string $shopwareVersion,
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

    public function getDependencies(): array
    {
        return [
            DocumentFormat::PDF->value,
            DocumentFormat::ZUGFERD_XML->value,
        ];
    }

    public function renderToString(RenderInput $input, RenderState $state, Context $context): RenderResult
    {
        $meta = $input->requireData(
            DocumentMetaProvider::KEY,
            DocumentMetaRenderData::class,
        );

        $pdf = $state->require(DocumentFormat::PDF->value)->content;
        $xml = $state->require(DocumentFormat::ZUGFERD_XML->value)->content;

        try {
            $content = (new ZugferdDocumentPdfMerger($xml, $pdf))
                ->setAdditionalCreatorTool(self::CREATOR_PREFIX . $this->shopwareVersion)
                ->generateDocument()
                ->downloadString();
        } catch (\Throwable $exception) {
            throw DocumentV2Exception::embedFailed($exception);
        }

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
