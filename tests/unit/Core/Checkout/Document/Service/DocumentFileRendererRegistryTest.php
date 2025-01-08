<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentFileRendererRegistry;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DocumentFileRendererRegistry::class)]
class DocumentFileRendererRegistryTest extends TestCase
{
    #[DataProvider('documentTypeRendererProvider')]
    public function testRender(RenderedDocument $document, \Closure $expectsClosure): void
    {
        $documentTemplateRenderer = $this->createMock(DocumentTemplateRenderer::class);
        $documentTemplateRenderer->expects(static::exactly(1))
            ->method('render')->willReturn('html');

        $pdfRenderer = new PdfRenderer(
            [],
            $documentTemplateRenderer,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $htmlRenderer = new HtmlRenderer(
            $documentTemplateRenderer,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $registry = new DocumentFileRendererRegistry(['pdf' => $pdfRenderer, 'html' => $htmlRenderer]);

        $config = new DocumentConfiguration();

        $document->setTemplateOptions([
            'template',
            ['config' => $config, 'order' => new OrderEntity(), 'context' => Context::createDefaultContext()],
            Context::createDefaultContext(),
            'salesChannelId',
            'languageId',
            'code',
        ]);

        $content = $registry->render($document);

        $expectsClosure($content);
    }

    public function testThrowException(): void
    {
        static::expectException(DocumentException::class);
        static::expectExceptionMessage('Invalid file extension: "pdf"');

        $registry = new DocumentFileRendererRegistry([]);

        $registry->render(new RenderedDocument(
            '',
            '1001',
            'invoice',
        ));
    }

    public static function documentTypeRendererProvider(): \Generator
    {
        yield 'PDF renderer' => [
            new RenderedDocument(
                'html',
                '1001',
                'invoice',
                PdfRenderer::FILE_EXTENSION,
                [],
                PdfRenderer::FILE_CONTENT_TYPE
            ),

            function (string $rendered): void {
                $finfo = new \finfo(\FILEINFO_MIME_TYPE);
                static::assertEquals('application/pdf', $finfo->buffer($rendered));
            },
        ];

        yield 'HTML renderer' => [
            new RenderedDocument(
                'html',
                '1001',
                'invoice',
                HtmlRenderer::FILE_EXTENSION,
                [],
                HtmlRenderer::FILE_CONTENT_TYPE
            ),

            function (string $rendered): void {
                static::assertSame($rendered, 'html');
            },
        ];
    }
}
