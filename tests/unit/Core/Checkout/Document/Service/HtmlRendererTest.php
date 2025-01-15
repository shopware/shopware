<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentException;
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
#[Package('after-sales')]
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
        $rendered->setTemplateOptions([
            '',
            [
                'config' => new DocumentConfiguration(),
            ],
        ]);
        $pre = $this->createMock(CallableClass::class);
        $pre->expects(static::once())->method('__invoke');
        $dispatcher->addListener(HtmlRendererExtension::NAME . '.pre', $pre);

        $post = $this->createMock(CallableClass::class);
        $post->expects(static::once())->method('__invoke');
        $dispatcher->addListener(HtmlRendererExtension::NAME . '.post', $post);

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
            HtmlRenderer::FILE_EXTENSION,
            ['displayFooter' => true],
            HtmlRenderer::FILE_CONTENT_TYPE,
        );

        $config = new DocumentConfiguration();
        $config->merge([
            'fileType' => PdfRenderer::FILE_EXTENSION,
            'itemsPerPage' => 10,
        ]);

        $rendered->setTemplateOptions([
            '',
            [
                'config' => $config,
            ],
        ]);

        static::assertStringContainsString('<html>', $rendered->getHtml());
        static::assertStringContainsString('</html>', $rendered->getHtml());
        static::assertStringContainsString('DOMPDF_PAGE_COUNT_PLACEHOLDER', $rendered->getHtml());

        $documentTemplateRenderer = $this->createMock(DocumentTemplateRenderer::class);
        $documentTemplateRenderer->expects(static::once())
            ->method('render')
            ->willReturn($html);

        $htmlRenderer = new HtmlRenderer(
            $documentTemplateRenderer,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $generatorOutput = $htmlRenderer->render($rendered);

        static::assertNotEmpty($config->getVars());
        static::assertSame($config->getVars()['fileType'], 'html');
        static::assertSame($config->getVars()['itemsPerPage'], 1000);

        static::assertNotEmpty($generatorOutput);
        static::assertEquals($html, $generatorOutput);

        static::assertSame(HtmlRenderer::FILE_EXTENSION, $rendered->getFileExtension());
        static::assertSame(HtmlRenderer::FILE_CONTENT_TYPE, $rendered->getContentType());
    }

    public function testRenderThrowException(): void
    {
        static::expectException(DocumentException::class);

        $rendered = new RenderedDocument(
            '',
            '1001',
            InvoiceRenderer::TYPE,
        );

        $htmlRenderer = new HtmlRenderer(
            $this->createMock(DocumentTemplateRenderer::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $htmlRenderer->render($rendered);
    }
}
