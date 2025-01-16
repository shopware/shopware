<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Extension\HtmlRendererExtension;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
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
        $htmlRenderer = new HtmlRenderer($this->createMock(DocumentTemplateRenderer::class), '', new ExtensionDispatcher(new EventDispatcher()));

        static::assertEquals('text/html', $htmlRenderer->getContentType());
    }

    public function testExtensionIsDispatched(): void
    {
        $dispatcher = new EventDispatcher();
        $renderer = new HtmlRenderer(
            $this->createMock(DocumentTemplateRenderer::class),
            '',
            new ExtensionDispatcher($dispatcher),
        );

        $rendered = new RenderedDocument(
            'html',
            '1001',
            InvoiceRenderer::TYPE,
            HtmlRenderer::FILE_EXTENSION,
        );

        $rendered->setOrder($this->getOrder());
        $rendered->setContext(Context::createDefaultContext());

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
            <html lang="en-GB">
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
            [],
            HtmlRenderer::FILE_CONTENT_TYPE,
        );

        $rendered->setContext(Context::createDefaultContext());
        $rendered->setOrder($this->getOrder());

        static::assertStringContainsString('<html lang="en-GB">', $rendered->getHtml());
        static::assertStringContainsString('</html>', $rendered->getHtml());
        static::assertStringContainsString('DOMPDF_PAGE_COUNT_PLACEHOLDER', $rendered->getHtml());

        $documentTemplateRenderer = $this->createMock(DocumentTemplateRenderer::class);
        $documentTemplateRenderer->expects(static::once())
            ->method('render')
            ->willReturn($html);

        $htmlRenderer = new HtmlRenderer(
            $documentTemplateRenderer,
            '',
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $generatorOutput = $htmlRenderer->render($rendered);

        static::assertNotEmpty($generatorOutput);
        static::assertEquals($html, $generatorOutput);

        static::assertSame(HtmlRenderer::FILE_EXTENSION, $rendered->getFileExtension());
        static::assertSame(HtmlRenderer::FILE_CONTENT_TYPE, $rendered->getContentType());
    }

    public function testRenderWithoutHtmlFormat(): void
    {
        $rendered = new RenderedDocument(
            'html',
            '1001',
            InvoiceRenderer::TYPE,
            HtmlRenderer::FILE_EXTENSION,
            ['fileTypes' => ['pdf']],
            HtmlRenderer::FILE_CONTENT_TYPE,
        );

        $documentTemplateRenderer = $this->createMock(DocumentTemplateRenderer::class);
        $documentTemplateRenderer->expects(static::never())
            ->method('render');

        $htmlRenderer = new HtmlRenderer(
            $documentTemplateRenderer,
            '',
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $generatorOutput = $htmlRenderer->render($rendered);

        static::assertEquals('', $generatorOutput);
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
            '',
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $htmlRenderer->render($rendered);
    }

    private function getOrder(): OrderEntity
    {
        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());
        $locale->setCode('en-GB');

        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());
        $language->setLocale($locale);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId($language->getId());
        $order->setLanguage($language);

        return $order;
    }
}
