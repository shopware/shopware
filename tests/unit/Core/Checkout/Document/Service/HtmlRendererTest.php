<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Extension\HtmlRendererExtension;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(HtmlRenderer::class)]
class HtmlRendererTest extends TestCase
{
    public function testGetContentType(): void
    {
        $htmlRenderer = new HtmlRenderer($this->createMock(DocumentTemplateRenderer::class), new ExtensionDispatcher(new EventDispatcher()));

        static::assertEquals('text/html', $htmlRenderer->getContentType());
    }

    public function testExtensionIsDispatched(): void
    {
        $dispatcher = new EventDispatcher();
        $renderer = new HtmlRenderer($this->createMock(DocumentTemplateRenderer::class), new ExtensionDispatcher($dispatcher));
        $rendered = new RenderedDocument('html', '1001', InvoiceRenderer::TYPE);

        $pre = $this->createMock(CallableClass::class);
        $pre->expects(static::once())->method('__invoke');
        $dispatcher->addListener(HtmlRendererExtension::NAME . '.pre', $pre);

        $post = $this->createMock(CallableClass::class);
        $post->expects(static::once())->method('__invoke');
        $dispatcher->addListener(HtmlRendererExtension::NAME . '.post', $post);

        $renderer->templateRenderer([], 'html');

        $renderer->render($rendered);
    }

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
            PdfRenderer::FILE_EXTENSION,
            ['displayFooter' => true]
        );

        static::assertSame(PdfRenderer::FILE_EXTENSION, $rendered->getFileExtension());
        static::assertSame(PdfRenderer::FILE_CONTENT_TYPE, $rendered->getContentType());

        static::assertStringContainsString('<html>', $rendered->getHtml());
        static::assertStringContainsString('</html>', $rendered->getHtml());
        static::assertStringContainsString('DOMPDF_PAGE_COUNT_PLACEHOLDER', $rendered->getHtml());

        $pdfRenderer = new HtmlRenderer(
            $this->createMock(DocumentTemplateRenderer::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );
        $pdfRenderer->templateRenderer([], $html);

        $generatorOutput = $pdfRenderer->render($rendered);

        static::assertNotEmpty($generatorOutput);
        static::assertEquals($html, $generatorOutput);

        static::assertSame(HtmlRenderer::FILE_EXTENSION, $rendered->getFileExtension());
        static::assertSame(HtmlRenderer::FILE_CONTENT_TYPE, $rendered->getContentType());
    }
}
