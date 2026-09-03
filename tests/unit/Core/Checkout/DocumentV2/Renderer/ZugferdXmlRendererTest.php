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
use Shopware\Core\Checkout\DocumentV2\Renderer\ZugferdXmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\DocumentV2\Template\DocumentTemplateRenderer;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\DocumentV2\Template\View\MonetarySummationView;
use Shopware\Core\Checkout\DocumentV2\Template\View\TradePartyView;
use Shopware\Core\Checkout\DocumentV2\Xml\XmlFormatter;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ZugferdXmlRenderer::class)]
class ZugferdXmlRendererTest extends TestCase
{
    private const ZUGFERD_TEMPLATE_PATH = '@Framework/documents/zugferd/invoice.xml.twig';

    public function testConfig(): void
    {
        $renderer = $this->createRenderer(
            static::createStub(TemplateFinder::class),
            static::createStub(TwigEnvironment::class),
        );

        static::assertSame(DocumentFormat::ZUGFERD_XML->value, $renderer->getFormat());
    }

    public function testRenderToString(): void
    {
        $rendered = '<root/>';

        $meta = $this->createMeta(filenamePrefix: 'zugferd_invoice_');
        $renderData = $this->createRenderData();

        $finder = $this->createMock(TemplateFinder::class);
        $finder->expects($this->once())
            ->method('find')
            ->with(self::ZUGFERD_TEMPLATE_PATH)
            ->willReturn(self::ZUGFERD_TEMPLATE_PATH);

        $env = $this->createMock(TwigEnvironment::class);
        $env->expects($this->once())
            ->method('renderWithTimezoneOverride')
            ->with(
                self::ZUGFERD_TEMPLATE_PATH,
                static::callback(function (array $parameters) use ($meta, $renderData): bool {
                    static::assertArrayHasKey('meta', $parameters);
                    static::assertSame($meta, $parameters['meta']);
                    static::assertArrayHasKey('renderData', $parameters);
                    static::assertSame($renderData, $parameters['renderData']);

                    return true;
                }),
                null,
            )
            ->willReturn($rendered);

        $renderer = $this->createRenderer($finder, $env);

        $result = $renderer->renderToString(
            $this->createInput($meta, $renderData),
            new RenderState(),
            Context::createDefaultContext(),
        );

        static::assertSame(DocumentFormat::ZUGFERD_XML->value, $result->format);
        static::assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $result->content);
        static::assertStringContainsString('<root/>', $result->content);
        static::assertSame('xml', $result->fileExtension);
        static::assertSame('application/xml', $result->mimeType);
        static::assertSame('zugferd_invoice_12345', $result->fileName);
    }

    public function testRenderToStringThrowsWhenTemplateProducesMalformedXml(): void
    {
        $finder = static::createStub(TemplateFinder::class);
        $finder->method('find')->willReturn(self::ZUGFERD_TEMPLATE_PATH);

        $env = static::createStub(TwigEnvironment::class);
        $env->method('renderWithTimezoneOverride')->willReturn('<root><unclosed></root>');

        $renderer = $this->createRenderer($finder, $env);

        $this->expectException(DocumentV2Exception::class);
        $this->expectExceptionMessageMatches('/Generated XML is malformed/');

        $renderer->renderToString(
            $this->createInput($this->createMeta(), $this->createRenderData()),
            new RenderState(),
            Context::createDefaultContext(),
        );
    }

    public function testResolvesTemplateByDocumentType(): void
    {
        $expectedTemplate = '@Framework/documents/zugferd/credit_note.xml.twig';

        $finder = $this->createMock(TemplateFinder::class);
        $finder->expects($this->once())
            ->method('find')
            ->with($expectedTemplate)
            ->willReturn($expectedTemplate);

        $env = static::createStub(TwigEnvironment::class);
        $env->method('renderWithTimezoneOverride')->willReturn('<root/>');

        $renderer = $this->createRenderer($finder, $env);

        $renderer->renderToString(
            new RenderInput(
                DocumentType::CREDIT_NOTE->value,
                '12345',
                $this->createOrder(),
                [
                    DocumentMetaProvider::KEY => $this->createMeta(),
                    DocumentType::CREDIT_NOTE->value => $this->createRenderData(),
                ],
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

    private function createRenderer(TemplateFinder $finder, TwigEnvironment $env): ZugferdXmlRenderer
    {
        return new ZugferdXmlRenderer(
            new DocumentTemplateRenderer(
                $finder,
                $env,
                static::createStub(AbstractTranslator::class),
                static::createStub(AbstractSalesChannelContextFactory::class),
                new EventDispatcher(),
                'rootDir',
            ),
            new XmlFormatter(),
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

    private function createInput(DocumentMetaRenderData $meta, InvoiceRenderData $data): RenderInput
    {
        return new RenderInput(
            DocumentType::INVOICE->value,
            '12345',
            $this->createOrder(),
            [
                DocumentMetaProvider::KEY => $meta,
                InvoiceDataProvider::KEY => $data,
            ],
        );
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

    private function createRenderData(): InvoiceRenderData
    {
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
        );
    }
}
