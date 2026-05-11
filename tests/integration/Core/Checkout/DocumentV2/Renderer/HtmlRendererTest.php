<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Renderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer as LegacyInvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer as LegacyHtmlRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Renderer\HtmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class HtmlRendererTest extends TestCase
{
    use DocumentTrait;
    use SnapshotTesting;

    private const DOCUMENT_NUMBER = '1000';

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private HtmlRenderer $renderer;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $shippingAddressId = Uuid::randomHex();
        $additionalAddress = [
            'id' => $shippingAddressId,
            'countryId' => $this->getValidCountryId(),
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'john',
            'lastName' => 'doe',
            'street' => 'example street 11',
            'zipcode' => '12345',
            'city' => 'example city',
        ];

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(
                    ['defaultShippingAddressId' => $shippingAddressId],
                    $additionalAddress,
                ),
            ],
        );

        $this->renderer = static::getContainer()->get(HtmlRenderer::class);
        $this->orderRepository = static::getContainer()->get('order.repository');
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(Translator::class)->reset();

        parent::tearDown();
    }

    /**
     * @param class-string<AbstractDocumentDataProvider> $dataProviderClass
     */
    #[DataProvider('provideHtmlDocumentTypes')]
    public function testRender(DocumentType $documentType, string $dataProviderClass): void
    {
        $dataProvider = static::getContainer()->get($dataProviderClass);
        static::assertInstanceOf(AbstractDocumentDataProvider::class, $dataProvider);

        $orderId = $this->persistCart($this->generateDemoCartWithTaxes([19, 7]));

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'orderNumber' => '10000',
                'orderDateTime' => '2026-05-05T12:00:00+00:00',
            ],
        ], $this->context);

        $criteria = new Criteria([$orderId]);
        $dataProvider->enrichOrderCriteria($criteria);

        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        $generationRequest = new DocumentGenerationRequest(
            orderId: $orderId,
            orderVersionId: Defaults::LIVE_VERSION,
            documentType: $documentType,
            requestedFormats: [DocumentFormat::HTML],
            documentNumber: self::DOCUMENT_NUMBER,
        );

        $renderData = $dataProvider->provideRenderingData(
            $order,
            $generationRequest,
            $this->context,
        );

        if ($renderData instanceof InvoiceRenderData) {
            $renderData->configuration->merge(self::getInvoiceComparisonConfig());
        }

        $input = new RenderInput(
            documentType: $documentType->value,
            documentNumber: self::DOCUMENT_NUMBER,
            order: $order,
            data: [$dataProvider->getKey() => $renderData],
        );

        $result = $this->renderer->renderToString(
            $input,
            new RenderState(),
            $this->context,
        );

        static::assertSame(DocumentFormat::HTML->value, $result->format);
        static::assertSame('html', $result->fileExtension);
        static::assertSame('text/html', $result->mimeType);

        $this->assertSnapshot('html_renderer_' . $documentType->value, [
            [
                'type' => self::TYPE_HTML,
                'actual' => $result->content,
            ],
        ]);
    }

    /**
     * @return iterable<string, array{
     *     documentType: DocumentType,
     *     dataProviderClass: class-string<AbstractDocumentDataProvider>
     * }>
     */
    public static function provideHtmlDocumentTypes(): iterable
    {
        yield 'invoice' => [
            'documentType' => DocumentType::INVOICE,
            'dataProviderClass' => InvoiceDataProvider::class,
        ];
        // yield 'delivery_note' ...
    }

    /**
     * @param class-string<AbstractDocumentDataProvider> $dataProviderClass
     * @param class-string<AbstractDocumentRenderer> $legacyRendererClass
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideLegacyHtmlDocumentTypes')]
    public function testOutputMatchesLegacyRenderer(
        DocumentType $documentType,
        string $dataProviderClass,
        string $legacyRendererClass,
        array $config,
    ): void {
        $dataProvider = static::getContainer()->get($dataProviderClass);
        static::assertInstanceOf(AbstractDocumentDataProvider::class, $dataProvider);

        $legacyRenderer = static::getContainer()->get($legacyRendererClass);
        static::assertInstanceOf(AbstractDocumentRenderer::class, $legacyRenderer);

        $orderId = $this->persistCart($this->generateDemoCartWithTaxes([7]));

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'orderNumber' => '10000',
                'orderDateTime' => '2026-05-05T12:00:00+00:00',
            ],
        ], $this->context);

        $legacyOperation = new DocumentGenerateOperation(
            $orderId,
            LegacyHtmlRenderer::FILE_EXTENSION,
            $config,
        );
        $legacyResult = $legacyRenderer->render(
            [$orderId => $legacyOperation],
            $this->context,
            new DocumentRendererConfig(),
        );

        $legacyDocument = $legacyResult->getSuccess()[$orderId] ?? null;
        static::assertNotNull($legacyDocument);

        $legacyContent = $legacyDocument->getContent();
        static::assertIsString($legacyContent);

        $criteria = new Criteria([$orderId]);
        $dataProvider->enrichOrderCriteria($criteria);

        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        $generationRequest = new DocumentGenerationRequest(
            orderId: $orderId,
            orderVersionId: Defaults::LIVE_VERSION,
            documentType: $documentType,
            requestedFormats: [DocumentFormat::HTML],
            documentNumber: self::DOCUMENT_NUMBER,
        );

        $renderData = $dataProvider->provideRenderingData(
            $order,
            $generationRequest,
            $this->context,
        );

        if ($renderData instanceof InvoiceRenderData) {
            $renderData->configuration->merge($config);
        }

        $input = new RenderInput(
            documentType: $documentType->value,
            documentNumber: self::DOCUMENT_NUMBER,
            order: $order,
            data: [$dataProvider->getKey() => $renderData],
        );

        $result = $this->renderer->renderToString(
            $input,
            new RenderState(),
            $this->context,
        );

        static::assertSame(
            self::normalizeHtml($legacyContent),
            self::normalizeHtml($result->content),
        );
    }

    /**
     * @return iterable<string, array{
     *     documentType: DocumentType,
     *     dataProviderClass: class-string<AbstractDocumentDataProvider>,
     *     legacyRendererClass: class-string<AbstractDocumentRenderer>,
     *     config: array<string, mixed>
     * }>
     */
    public static function provideLegacyHtmlDocumentTypes(): iterable
    {
        yield 'invoice' => [
            'documentType' => DocumentType::INVOICE,
            'dataProviderClass' => InvoiceDataProvider::class,
            'legacyRendererClass' => LegacyInvoiceRenderer::class,
            'config' => self::getInvoiceComparisonConfig(),
        ];
        // yield 'delivery_note' ...
    }

    /**
     * @return array<string, mixed>
     */
    private static function getInvoiceComparisonConfig(): array
    {
        return [
            'documentDate' => '2026-05-05T12:00:00+00:00',
            'documentComment' => 'comment.',
            'displayHeader' => true,
            'displayFooter' => true,
            'displayPrices' => true,
            'displayPageCount' => true,
            'displayLineItems' => true,
            'displayLineItemPosition' => true,
            'displayCompanyAddress' => true,
            'displayReturnAddress' => true,
            'displayDivergentDeliveryAddress' => true,
            'companyName' => 'Example Company',
            'companyPhone' => '+49 555 12345',
            'companyEmail' => 'info@example.com',
            'companyUrl' => 'https://example.com',
            'executiveDirector' => 'Jane Doe',
            'taxNumber' => 'DE123456789',
            'taxOffice' => 'Example Tax Office',
            'vatId' => 'DE987654321',
            'bankName' => 'Example Bank',
            'bankIban' => 'DE89370400440532013000',
            'bankBic' => 'COBADEFFXXX',
            'placeOfJurisdiction' => 'Example Place',
            'placeOfFulfillment' => 'Example Place',
            'documentNumber' => '1000',
        ];
    }
}
