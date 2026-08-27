<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Renderer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Renderer\HtmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\DocumentV2\Template\DocumentTemplateRenderer;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\DocumentV2\Template\PaginationCounter;
use Shopware\Core\Checkout\DocumentV2\Template\TemplateContext;
use Shopware\Core\Checkout\DocumentV2\Template\View\MonetarySummationView;
use Shopware\Core\Checkout\DocumentV2\Template\View\TradePartyView;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(HtmlRenderer::class)]
class HtmlRendererTest extends TestCase
{
    private const HTML_TEMPLATE_PATH = '@Framework/documents/invoice.html.twig';

    public function testConfig(): void
    {
        $renderer = $this->createRenderer(
            static::createStub(TemplateFinder::class),
            static::createStub(TwigEnvironment::class),
        );

        static::assertSame(DocumentFormat::HTML->value, $renderer->getFormat());
    }

    public function testRenderToString(): void
    {
        $rendered = '<html>rendered</html>';

        $meta = $this->createMeta(filenamePrefix: 'invoice_');
        $renderData = $this->createRenderData(custom: ['test' => 1]);

        $finder = $this->createMock(TemplateFinder::class);
        $finder->expects($this->once())
            ->method('find')
            ->with(self::HTML_TEMPLATE_PATH)
            ->willReturn(self::HTML_TEMPLATE_PATH);

        $env = $this->createMock(TwigEnvironment::class);
        $env->expects($this->once())
            ->method('renderWithTimezoneOverride')
            ->with(
                self::HTML_TEMPLATE_PATH,
                static::callback(function (array $parameters) use ($meta): bool {
                    static::assertArrayHasKey('config', $parameters);
                    static::assertInstanceOf(TemplateContext::class, $parameters['config']);
                    static::assertSame($meta->config->itemsPerPage, $parameters['config']->itemsPerPage);
                    static::assertSame(['test' => 1], $parameters['config']->offsetGet('custom'));

                    static::assertArrayHasKey('counter', $parameters);
                    static::assertInstanceOf(PaginationCounter::class, $parameters['counter']);

                    return true;
                }),
                null,
            )
            ->willReturn($rendered);

        $input = new RenderInput(
            DocumentType::INVOICE->value,
            $meta->documentNumber,
            $this->createOrder(),
            [
                DocumentMetaProvider::KEY => $meta,
                InvoiceDataProvider::KEY => $renderData,
            ],
        );

        $renderer = $this->createRenderer($finder, $env);

        $result = $renderer->renderToString(
            $input,
            new RenderState(),
            Context::createDefaultContext(),
        );

        static::assertSame(DocumentFormat::HTML->value, $result->format);
        static::assertSame($rendered, $result->content);
        static::assertSame('html', $result->fileExtension);
        static::assertSame('text/html', $result->mimeType);
        static::assertSame('invoice_12345', $result->fileName);
    }

    public function testResolvesTemplateByDocumentType(): void
    {
        $expectedTemplate = '@Framework/documents/credit_note.html.twig';

        $finder = $this->createMock(TemplateFinder::class);
        $finder->expects($this->once())
            ->method('find')
            ->with($expectedTemplate)
            ->willReturn($expectedTemplate);

        $env = static::createStub(TwigEnvironment::class);
        $env->method('renderWithTimezoneOverride')->willReturn('<html>rendered</html>');

        $renderer = $this->createRenderer($finder, $env);

        $renderer->renderToString(
            new RenderInput(
                DocumentType::CREDIT_NOTE->value,
                '12345',
                $this->createOrder(),
                [DocumentMetaProvider::KEY => $this->createMeta()],
            ),
            new RenderState(),
            Context::createDefaultContext(),
        );
    }

    public function testRejectsDocumentTypeThatIsNotATrustedIdentifier(): void
    {
        $finder = $this->createMock(TemplateFinder::class);
        $finder->expects($this->never())->method('find');

        $renderer = $this->createRenderer($finder, static::createStub(TwigEnvironment::class));

        $this->expectExceptionObject(DocumentV2Exception::invalidDocumentType('../invoice'));

        $renderer->renderToString(
            new RenderInput(
                '../invoice',
                '12345',
                $this->createOrder(),
                [InvoiceDataProvider::KEY => $this->createRenderData()],
            ),
            new RenderState(),
            Context::createDefaultContext(),
        );
    }

    public function testShouldThrowIfRenderDataCantBeFound(): void
    {
        $renderer = $this->createRenderer(
            static::createStub(TemplateFinder::class),
            static::createStub(TwigEnvironment::class),
        );

        $input = new RenderInput(
            DocumentType::INVOICE->value,
            '12345',
            $this->createOrder(),
            [],
        );

        $this->expectExceptionObject(
            DocumentV2Exception::unknownRenderData(DocumentMetaProvider::KEY, DocumentMetaRenderData::class),
        );

        $renderer->renderToString(
            $input,
            new RenderState(),
            Context::createDefaultContext(),
        );
    }

    private function createRenderer(TemplateFinder $finder, TwigEnvironment $env): HtmlRenderer
    {
        return new HtmlRenderer(
            new DocumentTemplateRenderer(
                $finder,
                $env,
                static::createStub(AbstractTranslator::class),
                static::createStub(AbstractSalesChannelContextFactory::class),
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

    private function createMeta(?string $filenamePrefix = null): DocumentMetaRenderData
    {
        return new DocumentMetaRenderData(
            config: new DocumentConfig(
                pageSize: 'a4',
                pageOrientation: 'portrait',
                itemsPerPage: 10,
                filenamePrefix: $filenamePrefix,
            ),
            company: new DocumentCompanyInfo(
                'company',
                'street',
                '12345',
                'city',
                new CountryEntity()
            ),
            display: new DocumentDisplayOptions(),
            documentDate: 'date',
            documentNumber: '12345',
            documentComment: null,
        );
    }

    /**
     * @param array<string, mixed> $custom
     */
    private function createRenderData(
        array $custom = [],
    ): InvoiceRenderData {
        return new InvoiceRenderData(
            typeCode: TypeCode::INVOICE,
            buyerReference: '10000',
            buyer: new TradePartyView(
                id: null,
                name: '',
                street: null,
                additionalAddressLine1: null,
                additionalAddressLine2: null,
                zipcode: null,
                city: null,
                countrySubdivision: null,
                countryIso: null,
                email: null,
            ),
            deliveryDate: null,
            lineItems: [],
            allowanceCharges: [],
            taxBreakdown: [],
            monetarySummation: new MonetarySummationView(0, 0, 0, 0, 0, 'EUR', 0, 0, 0, 0),
            paymentMeans: null,
            paymentDueDate: null,
            intraCommunityDelivery: false,
            custom: $custom,
        );
    }
}
