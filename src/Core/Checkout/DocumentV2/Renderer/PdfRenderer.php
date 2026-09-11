<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
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

    private const PAGE_COUNT_PLACEHOLDER = 'DOMPDF_PAGE_COUNT_PLACEHOLDER';

    /**
     * @param array<string, mixed> $dompdfOptions
     */
    public function __construct(
        private array $dompdfOptions,
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
            DocumentFormat::HTML->value,
        ];
    }

    public function renderToString(RenderInput $input, RenderState $state, Context $context): RenderResult
    {
        $html = $state->require(DocumentFormat::HTML->value)->content;

        $meta = $input->requireData(
            DocumentMetaProvider::KEY,
            DocumentMetaRenderData::class,
        );

        $config = $meta->config;

        $options = new Options($this->dompdfOptions);
        $options->setDefaultMediaType('print');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper($config->pageSize, $config->pageOrientation);
        $dompdf->loadHtml($html);
        $dompdf->render();

        $this->injectPageCount($dompdf);

        $content = $dompdf->output();
        $fileStem = $meta->config->buildFileStem($meta->documentNumber, self::FORMAT->value);

        return new RenderResult(
            format: self::FORMAT->value,
            content: $content,
            fileName: $fileStem,
            fileExtension: self::FORMAT->fileExtension(),
            mimeType: self::FORMAT->mimeType(),
        );
    }

    /**
     * Replaces the literal `DOMPDF_PAGE_COUNT_PLACEHOLDER` emitted by the footer Twig with the
     * real page count after rendering.
     *
     * Unicode TrueType fonts encode text in the CPDF stream as UTF-16BE (null-byte padded),
     * while built-in standard 14 AFM fonts (such as Helvetica, when external fonts are blocked
     * or fallback is used) encode text as single-byte strings. Both encodings are replaced.
     */
    private function injectPageCount(Dompdf $dompdf): void
    {
        /** @var CPDF $canvas */
        $canvas = $dompdf->getCanvas();
        $pageCount = (string) $canvas->get_page_count();

        $search = [
            $this->insertNullByteBeforeEachCharacter(self::PAGE_COUNT_PLACEHOLDER),
            self::PAGE_COUNT_PLACEHOLDER,
        ];
        $replace = [
            $this->insertNullByteBeforeEachCharacter($pageCount),
            $pageCount,
        ];

        $pdf = $canvas->get_cpdf();

        foreach ($pdf->objects as &$o) {
            if ($o['t'] === 'contents') {
                $o['c'] = str_replace($search, $replace, (string) $o['c']);
            }
        }

        unset($o);
    }

    private function insertNullByteBeforeEachCharacter(string $string): string
    {
        return "\u{0000}" . substr(chunk_split($string, 1, "\u{0000}"), 0, -1);
    }
}
