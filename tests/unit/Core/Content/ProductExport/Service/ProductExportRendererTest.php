<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Service;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderFooterContextEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderHeaderContextEvent;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\ProductExportException;
use Shopware\Core\Content\ProductExport\Service\ProductExportRenderer;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductExportRenderer::class)]
class ProductExportRendererTest extends TestCase
{
    private readonly SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->context = Generator::generateSalesChannelContext();
    }

    #[DataProvider('renderHeaderProvider')]
    public function testRenderHeader(?string $headerTemplate, string $expected, string $domainUrl = 'http://de.test'): void
    {
        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setHeaderTemplate($headerTemplate);

        $domain = new SalesChannelDomainEntity();
        $domain->setUrl($domainUrl);

        $productExport->setSalesChannelDomain($domain);

        $event = new ProductExportRenderHeaderContextEvent(
            [
                'productExport' => $productExport,
                'context' => $this->context,
            ]
        );

        $dispatcher = static::createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturn($event);

        $environment = new Environment(new ArrayLoader());

        $twigRenderer = new StringTemplateRenderer($environment, sys_get_temp_dir());

        $renderer = new ProductExportRenderer(
            $twigRenderer,
            $dispatcher,
        );

        $rendered = $renderer->renderHeader($productExport, $this->context);

        static::assertSame($expected, $rendered);
    }

    public function testRenderHeaderError(): void
    {
        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setHeaderTemplate('content');

        $event = new ProductExportRenderHeaderContextEvent(
            [
                'productExport' => $productExport,
                'context' => $this->context,
            ]
        );
        $loggingEvent = new ProductExportLoggingEvent(
            $this->context->getContext(),
            'error',
            Level::Warning,
            ProductExportException::renderHeaderException('error')
        );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $dispatcher->expects($this->exactly(2))->method('dispatch')->willReturnOnConsecutiveCalls($event, $loggingEvent);

        $twigRenderer = $this->createMock(StringTemplateRenderer::class);
        $twigRenderer->expects($this->once())->method('render')->willThrowException(AdapterException::renderingTemplateFailed('error'));

        $renderer = new ProductExportRenderer(
            $twigRenderer,
            $dispatcher,
        );

        $this->expectExceptionObject(ProductExportException::renderHeaderException(AdapterException::renderingTemplateFailed('error')->getMessage()));

        $renderer->renderHeader($productExport, $this->context);
    }

    #[DataProvider('renderHeaderProvider')]
    public function testRenderFooter(?string $footerTemplate, string $expected, string $domainUrl = 'http://de.test'): void
    {
        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setFooterTemplate($footerTemplate);

        $domain = new SalesChannelDomainEntity();
        $domain->setUrl($domainUrl);

        $productExport->setSalesChannelDomain($domain);

        $event = new ProductExportRenderFooterContextEvent(
            [
                'productExport' => $productExport,
                'context' => $this->context,
            ]
        );

        $dispatcher = static::createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturn($event);

        $environment = new Environment(new ArrayLoader());

        $twigRenderer = new StringTemplateRenderer($environment, sys_get_temp_dir());

        $renderer = new ProductExportRenderer(
            $twigRenderer,
            $dispatcher,
        );

        $rendered = $renderer->renderFooter($productExport, $this->context);

        static::assertSame($expected, $rendered);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('renderBodyProvider')]
    public function testRenderBody(?string $bodyTemplate, string $expected, array $data, string $domainUrl = 'http://de.test'): void
    {
        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setBodyTemplate($bodyTemplate);

        $domain = new SalesChannelDomainEntity();
        $domain->setUrl($domainUrl);

        $productExport->setSalesChannelDomain($domain);

        $dispatcher = static::createStub(EventDispatcherInterface::class);

        $environment = new Environment(new ArrayLoader());

        $twigRenderer = new StringTemplateRenderer($environment, sys_get_temp_dir());

        $renderer = new ProductExportRenderer(
            $twigRenderer,
            $dispatcher,
        );

        $rendered = $renderer->renderBody($productExport, $this->context, $data);

        static::assertSame($expected, $rendered);
    }

    public function testRenderFooterError(): void
    {
        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setFooterTemplate('content');

        $event = new ProductExportRenderFooterContextEvent(
            [
                'productExport' => $productExport,
                'context' => $this->context,
            ]
        );
        $loggingEvent = new ProductExportLoggingEvent(
            $this->context->getContext(),
            'error',
            Level::Warning,
            ProductExportException::renderProductException('error')
        );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $dispatcher->expects($this->exactly(2))->method('dispatch')->willReturnOnConsecutiveCalls($event, $loggingEvent);

        $twigRenderer = $this->createMock(StringTemplateRenderer::class);
        $twigRenderer->expects($this->once())->method('render')->willThrowException(AdapterException::renderingTemplateFailed('error'));

        $renderer = new ProductExportRenderer(
            $twigRenderer,
            $dispatcher,
        );

        $this->expectExceptionObject(ProductExportException::renderFooterException(AdapterException::renderingTemplateFailed('error')->getMessage()));

        $renderer->renderFooter($productExport, $this->context);
    }

    public function testRenderEmptyBody(): void
    {
        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setBodyTemplate(null);

        $dispatcher = static::createStub(EventDispatcherInterface::class);
        $twigRenderer = static::createStub(StringTemplateRenderer::class);

        $renderer = new ProductExportRenderer(
            $twigRenderer,
            $dispatcher,
        );

        $this->expectExceptionObject(ProductExportException::templateBodyNotSet());

        $renderer->renderBody($productExport, $this->context, []);
    }

    public function testRenderBodyError(): void
    {
        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setBodyTemplate('content');

        $loggingEvent = new ProductExportLoggingEvent(
            $this->context->getContext(),
            'error',
            Level::Warning,
            ProductExportException::renderProductException('error')
        );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $dispatcher->expects($this->once())->method('dispatch')->willReturn($loggingEvent);

        $twigRenderer = $this->createMock(StringTemplateRenderer::class);
        $twigRenderer->expects($this->once())->method('render')->willThrowException(AdapterException::renderingTemplateFailed('error'));

        $renderer = new ProductExportRenderer(
            $twigRenderer,
            $dispatcher,
        );

        $this->expectExceptionObject(ProductExportException::renderProductException(AdapterException::renderingTemplateFailed('error')->getMessage()));

        $renderer->renderBody($productExport, $this->context, []);
    }

    public function testRenderBodyEncodesUrlsInNestedData(): void
    {
        $renderer = $this->createRenderer();
        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setBodyTemplate('{{ custom.nested.url }}');

        $rendered = $renderer->renderBody($productExport, $this->context, [
            'custom' => [
                'nested' => [
                    'url' => 'https://example.com/media/My Image, Front.jpg?width=100#preview',
                ],
            ],
        ]);

        static::assertSame('https://example.com/media/My%20Image,%20Front.jpg?width=100#preview' . \PHP_EOL, $rendered);
    }

    public function testRenderBodyEncodesUrlsInNestedStructsWithoutMutatingThem(): void
    {
        $renderer = $this->createRenderer();
        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setBodyTemplate('{{ cover.media.url }}');

        $media = new MediaEntity();
        $media->setUrl('https://example.com/media/My Image, Front.jpg');

        $cover = new ProductMediaEntity();
        $cover->setMedia($media);

        $rendered = $renderer->renderBody($productExport, $this->context, ['cover' => $cover]);

        static::assertSame('https://example.com/media/My%20Image,%20Front.jpg' . \PHP_EOL, $rendered);

        static::assertSame('https://example.com/media/My Image, Front.jpg', $media->getUrl());
        $coverMedia = $cover->getMedia();
        static::assertNotNull($coverMedia);
        static::assertSame('https://example.com/media/My Image, Front.jpg', $coverMedia->getUrl());
    }

    public function testRenderBodyKeepsUnchangedStructsByIdentity(): void
    {
        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setBodyTemplate('{{ cover.media.url }}');

        $media = new MediaEntity();
        $media->setUrl('https://example.com/media/My Image.jpg');

        $cover = new ProductMediaEntity();
        $cover->setMedia($media);

        $unchanged = new ArrayStruct(['label' => 'Product feed']);

        $twigRenderer = $this->createMock(StringTemplateRenderer::class);
        $twigRenderer->expects($this->once())->method('render')->willReturnCallback(
            static function (string $template, array $data) use ($cover, $unchanged): string {
                static::assertSame('{{ cover.media.url }}', $template);
                static::assertNotSame($cover, $data['cover']);
                static::assertSame($unchanged, $data['unchanged']);

                return 'rendered';
            }
        );

        $renderer = new ProductExportRenderer(
            $twigRenderer,
            static::createStub(EventDispatcherInterface::class),
        );

        static::assertSame('rendered' . \PHP_EOL, $renderer->renderBody($productExport, $this->context, [
            'cover' => $cover,
            'unchanged' => $unchanged,
        ]));
    }

    public function testRenderBodyDoesNotDoubleEncodeUrls(): void
    {
        $renderer = $this->createRenderer();
        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setBodyTemplate('{{ url }}');

        $rendered = $renderer->renderBody($productExport, $this->context, [
            'url' => 'https://example.com/media/My%20Image,%20Front.jpg?foo=hello%20world',
        ]);

        static::assertSame('https://example.com/media/My%20Image,%20Front.jpg?foo=hello%20world' . \PHP_EOL, $rendered);
    }

    public function testRenderBodyKeepsNonUrlAndUnparsableUrlValues(): void
    {
        $renderer = $this->createRenderer();
        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setBodyTemplate('{{ label }} {{ url }}');

        $rendered = $renderer->renderBody($productExport, $this->context, [
            'label' => 'Product feed',
            'url' => 'https://',
        ]);

        static::assertSame('Product feed https://' . \PHP_EOL, $rendered);
    }

    public function testRenderBodyKeepsOriginalValueForInvalidUrls(): void
    {
        $renderer = $this->createRenderer();
        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setBodyTemplate('{{ url }}');

        $rendered = $renderer->renderBody($productExport, $this->context, [
            'url' => 'https://cdn.example.com:99999/image.jpg',
        ]);

        static::assertSame('https://cdn.example.com:99999/image.jpg' . \PHP_EOL, $rendered);
    }

    /**
     * @return iterable<string, array<int, string|null>>
     */
    public static function renderHeaderProvider(): iterable
    {
        yield 'null' => [
            null,
            '',
        ];
        yield 'empty' => [
            '',
            \PHP_EOL,
        ];
        yield 'plain' => [
            'this is a plain string',
            'this is a plain string' . \PHP_EOL,
        ];

        yield 'with domain url in template' => [
            'this is a with http://en.test in template',
            'this is a with http://en.test in template' . \PHP_EOL,
            'http://en.test',
        ];
    }

    /**
     * @return iterable<string, array<int, string|array<string, mixed>|null>>
     */
    public static function renderBodyProvider(): iterable
    {
        yield 'empty' => [
            '',
            \PHP_EOL,
            [],
        ];

        yield 'plain' => [
            'this is a plain string',
            'this is a plain string' . \PHP_EOL, [],
        ];

        yield 'with correct domain url in template' => [
            'this is a with http://de.test in template',
            'this is a with http://de.test in template' . \PHP_EOL,
            [],
            'http://en.test',
        ];
    }

    private function createRenderer(): ProductExportRenderer
    {
        return new ProductExportRenderer(
            new StringTemplateRenderer(new Environment(new ArrayLoader()), sys_get_temp_dir()),
            static::createStub(EventDispatcherInterface::class),
        );
    }
}
