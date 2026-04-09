<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedCancellationInvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\ZugferdEmbeddedService;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ZugferdEmbeddedCancellationInvoiceRenderer::class)]
class ZugferdEmbeddedCancellationInvoiceRendererTest extends TestCase
{
    public function testSupports(): void
    {
        $renderer = new ZugferdEmbeddedCancellationInvoiceRenderer(
            $this->createMock(AbstractDocumentRenderer::class),
            $this->createMock(AbstractDocumentRenderer::class),
            new ZugferdEmbeddedService(),
            'version'
        );

        static::assertSame('zugferd_embedded_cancellation_invoice', $renderer->supports());
    }

    public function testRenderCallsService(): void
    {
        $xml = \file_get_contents(__DIR__ . '/_fixtures/invoice_1.xml');
        static::assertIsString($xml);

        $pdf = \file_get_contents(__DIR__ . '/_fixtures/invoice_1.pdf');
        static::assertIsString($pdf);

        $baseResult = new RendererResult();
        $baseResult->addSuccess('order1', new RenderedDocument(content: $pdf));

        $electronicResult = new RendererResult();
        $electronicResult->addSuccess('order1', new RenderedDocument(content: $xml));

        $cancellationInvoiceRenderer = $this->createMock(AbstractDocumentRenderer::class);
        $cancellationInvoiceRenderer
            ->method('render')
            ->willReturn($baseResult);

        $electronicRenderer = $this->createMock(AbstractDocumentRenderer::class);
        $electronicRenderer
            ->method('render')
            ->willReturn($electronicResult);

        $renderer = new ZugferdEmbeddedCancellationInvoiceRenderer(
            $cancellationInvoiceRenderer,
            $electronicRenderer,
            new ZugferdEmbeddedService(),
            'version'
        );

        $result = $renderer->render(
            ['order1' => new DocumentGenerateOperation('order1')],
            Context::createDefaultContext(),
            new DocumentRendererConfig()
        );

        $renderedDocument = $result->getOrderSuccess('order1');
        static::assertInstanceOf(RenderedDocument::class, $renderedDocument);
    }
}
