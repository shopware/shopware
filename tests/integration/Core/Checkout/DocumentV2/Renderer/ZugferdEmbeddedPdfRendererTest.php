<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Renderer;

use horstoeko\zugferd\ZugferdDocumentPdfReader;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Renderer\HtmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Renderer\PdfRenderer;
use Shopware\Core\Checkout\DocumentV2\Renderer\ZugferdEmbeddedPdfRenderer;
use Shopware\Core\Checkout\DocumentV2\Renderer\ZugferdXmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;

/**
 * @internal
 */
#[Package('after-sales')]
class ZugferdEmbeddedPdfRendererTest extends TestCase
{
    use DocumentV2Trait;

    private HtmlRenderer $htmlRenderer;

    private ZugferdXmlRenderer $xmlRenderer;

    private PdfRenderer $pdfRenderer;

    private ZugferdEmbeddedPdfRenderer $embeddedRenderer;

    private InvoiceDataProvider $dataProvider;

    private DocumentMetaProvider $metaProvider;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $shippingAddressId = Uuid::randomHex();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(
                    ['defaultShippingAddressId' => $shippingAddressId],
                    $this->buildDemoShippingAddress($shippingAddressId),
                ),
            ],
        );

        $this->htmlRenderer = static::getContainer()->get(HtmlRenderer::class);
        $this->xmlRenderer = static::getContainer()->get(ZugferdXmlRenderer::class);
        $this->pdfRenderer = static::getContainer()->get(PdfRenderer::class);
        $this->embeddedRenderer = static::getContainer()->get(ZugferdEmbeddedPdfRenderer::class);
        $this->dataProvider = static::getContainer()->get(InvoiceDataProvider::class);
        $this->metaProvider = static::getContainer()->get(DocumentMetaProvider::class);
        $this->orderRepository = static::getContainer()->get('order.repository');

        $this->seedDemoBaseConfig(DocumentType::INVOICE->value);
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(Translator::class)->reset();

        parent::tearDown();
    }

    public function testRendersPdfWithEmbeddedZugferdXml(): void
    {
        $orderId = $this->persistCart($this->generateDemoCartWithTaxes([19, 7]));
        $this->enrichOrderForRendering($orderId);

        $criteria = new Criteria([$orderId]);
        $this->dataProvider->enrichOrderCriteria($criteria);

        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        $request = new DocumentGenerationRequest(
            orderId: $orderId,
            documentType: DocumentType::INVOICE,
            requestedFormats: [DocumentFormat::ZUGFERD_EMBEDDED_PDF],
            documentNumber: self::DOCUMENT_NUMBER,
            documentDate: self::DOCUMENT_DATE,
        );

        $input = new RenderInput(
            documentType: DocumentType::INVOICE->value,
            documentNumber: self::DOCUMENT_NUMBER,
            order: $order,
            data: [
                $this->metaProvider->getKey() => $this->metaProvider->provideRenderingData(new ProviderInput($order, $request), $this->context),
                $this->dataProvider->getKey() => $this->dataProvider->provideRenderingData(new ProviderInput($order, $request), $this->context),
            ],
        );

        $state = new RenderState();
        $state->add($this->htmlRenderer->renderToString($input, $state, $this->context));
        $state->add($this->xmlRenderer->renderToString($input, $state, $this->context));
        $state->add($this->pdfRenderer->renderToString($input, $state, $this->context));

        $result = $this->embeddedRenderer->renderToString($input, $state, $this->context);

        static::assertSame(DocumentFormat::ZUGFERD_EMBEDDED_PDF->value, $result->format);
        static::assertSame('invoice_' . self::DOCUMENT_NUMBER . '_zugferd', $result->fileName);
        static::assertSame('pdf', $result->fileExtension);
        static::assertSame('application/pdf', $result->mimeType);
        static::assertStringStartsWith('%PDF-', $result->content);

        $embeddedXml = ZugferdDocumentPdfReader::getXmlFromContent($result->content);
        static::assertStringContainsString('CrossIndustryInvoice', $embeddedXml);
        static::assertStringContainsString('xrechnung_3.0', $embeddedXml);
    }
}
