<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Renderer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Renderer\HtmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\DocumentV2\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\DocumentV2\Twig\PaginationCounter;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Twig\Environment;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(HtmlRenderer::class)]
class HtmlRendererTest extends TestCase
{
    public function testConfig(): void
    {
        $renderer = $this->createRenderer(
            $this->createMock(TemplateFinder::class),
            $this->createMock(Environment::class),
        );

        static::assertSame(DocumentFormat::HTML->value, $renderer->getFormat());
        static::assertSame([DocumentType::INVOICE->value], $renderer->getDocumentTypes());
    }

    public function testRenderToString(): void
    {
        $rendered = '<html>rendered</html>';

        $config = new DocumentConfiguration();
        $config->merge([
            'fileType' => 'pdf',
            'itemsPerPage' => 10,
            'documentNumber' => '12345',
            'filenamePrefix' => 'invoice_',
            'custom' => ['test' => 1],
        ]);

        $finder = $this->createMock(TemplateFinder::class);
        $finder->expects($this->once())
            ->method('find')
            ->with(DocumentType::INVOICE->templatePath())
            ->willReturn(DocumentType::INVOICE->templatePath());

        $env = $this->createMock(Environment::class);
        $env->expects($this->once())
            ->method('render')
            ->with(
                DocumentType::INVOICE->templatePath(),
                static::callback(function (array $parameters) use ($config): bool {
                    static::assertArrayHasKey('config', $parameters);
                    static::assertInstanceOf(DocumentConfiguration::class, $parameters['config']);
                    static::assertNotSame($config, $parameters['config']);
                    static::assertSame('html', $parameters['config']->__get('fileType'));
                    static::assertSame(1000, $parameters['config']->__get('itemsPerPage'));
                    static::assertSame(['test' => 1], $parameters['config']->__get('custom'));

                    static::assertArrayHasKey('counter', $parameters);
                    static::assertInstanceOf(PaginationCounter::class, $parameters['counter']);

                    return true;
                })
            )
            ->willReturn($rendered);

        $renderer = $this->createRenderer($finder, $env);

        $result = $renderer->renderToString(
            $this->createInput($config),
            new RenderState(),
            Context::createDefaultContext(),
        );

        static::assertSame(DocumentFormat::HTML->value, $result->format);
        static::assertSame($rendered, $result->content);
        static::assertSame('html', $result->fileExtension);
        static::assertSame('text/html', $result->mimeType);
        static::assertSame('invoice_12345', $result->fileName);

        static::assertSame('pdf', $config->__get('fileType'));
        static::assertSame(10, $config->__get('itemsPerPage'));
    }

    public function testShouldThrowIfRenderDataCantBeFound(): void
    {
        $renderer = $this->createRenderer(
            $this->createMock(TemplateFinder::class),
            $this->createMock(Environment::class),
        );

        $input = new RenderInput(
            DocumentType::INVOICE->value,
            '12345',
            $this->createOrder(),
            [],
        );

        static::expectExceptionObject(
            DocumentV2Exception::unknownRenderData(InvoiceDataProvider::KEY, InvoiceRenderData::class),
        );

        $renderer->renderToString(
            $input,
            new RenderState(),
            Context::createDefaultContext(),
        );
    }

    public function testShouldThrowIfDocumentTypeDoesNotExist(): void
    {
        $renderer = $this->createRenderer(
            $this->createMock(TemplateFinder::class),
            $this->createMock(Environment::class),
        );

        $input = new RenderInput(
            'unknown_document_type',
            '12345',
            $this->createOrder(),
            [InvoiceDataProvider::KEY => new InvoiceRenderData(new DocumentConfiguration())],
        );

        static::expectException(\ValueError::class);

        $renderer->renderToString(
            $input,
            new RenderState(),
            Context::createDefaultContext(),
        );
    }

    private function createRenderer(TemplateFinder $finder, Environment $env): HtmlRenderer
    {
        return new HtmlRenderer(
            new DocumentTemplateRenderer(
                $finder,
                $env,
                $this->createMock(AbstractTranslator::class),
                $this->createMock(AbstractSalesChannelContextFactory::class),
                'rootDir',
            ),
        );
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId(Uuid::randomHex());

        return $order;
    }

    private function createInput(DocumentConfiguration $config): RenderInput
    {
        return new RenderInput(
            DocumentType::INVOICE->value,
            '12345',
            $this->createOrder(),
            [InvoiceDataProvider::KEY => new InvoiceRenderData($config)],
        );
    }
}
