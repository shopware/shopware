<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;

/**
 * @package checkout
 *
 * @internal
 */
#[CoversClass(PdfRenderer::class)]
class PdfRendererTest extends TestCase
{
    public function testRender(): void
    {
        $html = '
            <!DOCTYPE html>
            <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                    <title>Delivery note 1000 for Order 10000</title>
                </head>
                <body>
                    <footer>
                        <div class="page-count">
                            Page <span class="pagenum"></span> / DOMPDF_PAGE_COUNT_PLACEHOLDER
                        </div>
                    </footer>
                </body>
            </html>
        ';

        $rendered = new RenderedDocument(
            $html,
            '1001',
            InvoiceRenderer::TYPE,
            FileTypes::PDF,
            ['displayFooter' => true]
        );

        static::assertStringContainsString('<html>', $rendered->getHtml());
        static::assertStringContainsString('</html>', $rendered->getHtml());
        static::assertStringContainsString('DOMPDF_PAGE_COUNT_PLACEHOLDER', $rendered->getHtml());

        $pdfRenderer = new PdfRenderer([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ]);
        $generatorOutput = $pdfRenderer->render($rendered);
        static::assertNotEmpty($generatorOutput);

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        static::assertEquals('application/pdf', $finfo->buffer($generatorOutput));
    }
}
