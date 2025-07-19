<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Renderer;

use horstoeko\zugferd\exception\ZugferdUnknownXmlContentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\PdfParser\PdfParserException;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ZugferdEmbeddedRenderer::class)]
class ZugferdEmbeddedRendererTest extends TestCase
{
    public function testSupports(): void
    {
        $renderer = new ZugferdEmbeddedRenderer(
            $this->createMock(AbstractDocumentRenderer::class),
            $this->createMock(AbstractDocumentRenderer::class),
            'random-version'
        );

        static::assertSame('zugferd_embedded_invoice', $renderer->supports());
    }

    public function testRender(): void
    {
        $invoiceResult = new RendererResult();
        $invoiceResult->addSuccess('success', new RenderedDocument(content: $this->getPDFContent()));
        $invoiceResult->addSuccess('emptyXML', new RenderedDocument(content: $this->getPDFContent()));
        $invoiceResult->addSuccess('emptyPDF', new RenderedDocument());
        $invoiceResult->addSuccess('invoiceSuccess', new RenderedDocument(content: $this->getPDFContent()));
        $invoiceResult->addSuccess('missingZugferd', new RenderedDocument());
        $invoiceResult->addError('zugferdSuccess', new \RuntimeException('invoice broken'));

        $invoiceRenderer = $this->createMock(AbstractDocumentRenderer::class);
        $invoiceRenderer
            ->method('render')
            ->willReturn($invoiceResult);

        $zugferdResult = new RendererResult();
        $zugferdResult->addSuccess('success', new RenderedDocument(content: $this->getXMLContent()));
        $zugferdResult->addSuccess('emptyXML', new RenderedDocument());
        $zugferdResult->addSuccess('emptyPDF', new RenderedDocument(content: $this->getXMLContent()));
        $zugferdResult->addError('invoiceSuccess', new \RuntimeException('zugferd document broken'));
        $zugferdResult->addSuccess('zugferdSuccess', new RenderedDocument(content: $this->getXMLContent()));

        $zugferdRenderer = $this->createMock(AbstractDocumentRenderer::class);
        $zugferdRenderer
            ->method('render')
            ->willReturn($zugferdResult);

        $embeddedRenderer = new ZugferdEmbeddedRenderer($invoiceRenderer, $zugferdRenderer, 'random-version');

        $result = $embeddedRenderer->render([
            'success' => new DocumentGenerateOperation('success'),
            'emptyXML' => new DocumentGenerateOperation('emptyXML'),
            'emptyPDF' => new DocumentGenerateOperation('emptyPDF'),
            'invoiceSuccess' => new DocumentGenerateOperation('invoiceSuccess'),
            'missingZugferd' => new DocumentGenerateOperation('missingZugferd'),
            'zugferdSuccess' => new DocumentGenerateOperation('zugferdSuccess'),
        ], Context::createDefaultContext(), new DocumentRendererConfig());

        static::assertInstanceOf(RenderedDocument::class, $result->getOrderSuccess('success'));

        static::assertInstanceOf(ZugferdUnknownXmlContentException::class, $result->getOrderError('emptyXML'));
        static::assertInstanceOf(PdfParserException::class, $result->getOrderError('emptyPDF'));
        static::assertInstanceOf(\RuntimeException::class, $result->getOrderError('invoiceSuccess'));
        static::assertInstanceOf(\RuntimeException::class, $result->getOrderError('missingZugferd'));
        static::assertInstanceOf(\RuntimeException::class, $result->getOrderError('zugferdSuccess'));
    }

    protected function getXMLContent(): string
    {
        $content = file_get_contents(__DIR__ . '/_fixtures/invoice_1.xml');

        static::assertIsString($content);

        return $content;
    }

    protected function getPDFContent(): string
    {
        $content = file_get_contents(__DIR__ . '/_fixtures/invoice_1.pdf');

        static::assertIsString($content);

        return $content;
    }
}
