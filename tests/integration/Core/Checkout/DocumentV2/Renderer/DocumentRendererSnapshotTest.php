<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Renderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer as LegacyInvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer as LegacyHtmlRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Renderer\HtmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Renderer\XmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\DocumentV2\Template\View\AllowanceChargeView;
use Shopware\Core\Checkout\DocumentV2\Template\View\LineItemView;
use Shopware\Core\Checkout\DocumentV2\Template\View\MonetarySummationView;
use Shopware\Core\Checkout\DocumentV2\Template\View\PaymentMeansView;
use Shopware\Core\Checkout\DocumentV2\Template\View\TaxBreakdownView;
use Shopware\Core\Checkout\DocumentV2\Template\View\TradePartyView;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentRendererSnapshotTest extends TestCase
{
    use DocumentTrait;
    use PromotionTestFixtureBehaviour;
    use SnapshotTesting;

    private const DOCUMENT_NUMBER = '1000';

    private const DOCUMENT_DATE = '2026-05-05T12:00:00+00:00';

    private Context $context;

    private SalesChannelContext $salesChannelContext;

    private HtmlRenderer $htmlRenderer;

    private XmlRenderer $xmlRenderer;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private CountryEntity $companyCountry;

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

        $this->htmlRenderer = static::getContainer()->get(HtmlRenderer::class);
        $this->xmlRenderer = static::getContainer()->get(XmlRenderer::class);
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->companyCountry = $this->loadCompanyCountry();
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(Translator::class)->reset();

        parent::tearDown();
    }

    /**
     * @param class-string<AbstractDocumentDataProvider> $dataProviderClass
     */
    #[DataProvider('provideDocumentTypes')]
    public function testRender(DocumentType $documentType, string $dataProviderClass): void
    {
        $dataProvider = static::getContainer()->get($dataProviderClass);
        static::assertInstanceOf(AbstractDocumentDataProvider::class, $dataProvider);

        $cart = $this->generateDemoCartWithTaxes([19, 7]);
        $cart = $this->applyTenPercentPromotion($cart);
        $orderId = $this->persistCart($cart);

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'orderNumber' => '10000',
                'orderDateTime' => self::DOCUMENT_DATE,
            ],
        ], $this->context);

        $criteria = new Criteria([$orderId]);
        $dataProvider->enrichOrderCriteria($criteria);

        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        $input = new RenderInput(
            documentType: $documentType->value,
            documentNumber: self::DOCUMENT_NUMBER,
            order: $order,
            data: [$dataProvider->getKey() => $this->buildRenderData($documentType, $order)],
        );

        $htmlResult = $this->htmlRenderer->renderToString($input, new RenderState(), $this->context);
        $xmlResult = $this->xmlRenderer->renderToString($input, new RenderState(), $this->context);

        static::assertSame(DocumentFormat::HTML->value, $htmlResult->format);
        static::assertSame(DocumentFormat::ZUGFERD_XML->value, $xmlResult->format);

        $this->assertSnapshot($documentType->value, [
            [
                'type' => self::TYPE_HTML,
                'actual' => $htmlResult->content,
            ],
            [
                'type' => self::TYPE_XML,
                'actual' => $xmlResult->content,
            ],
        ]);
    }

    /**
     * @return iterable<string, array{DocumentType, class-string<AbstractDocumentDataProvider>}>
     */
    public static function provideDocumentTypes(): iterable
    {
        yield 'invoice' => [
            DocumentType::INVOICE,
            InvoiceDataProvider::class,
        ];
    }

    /**
     * @param class-string<AbstractDocumentDataProvider> $dataProviderClass
     * @param class-string<AbstractDocumentRenderer> $legacyRendererClass
     */
    #[DataProvider('provideLegacyDocumentTypes')]
    public function testOutputMatchesLegacyRenderer(
        DocumentType $documentType,
        string $dataProviderClass,
        string $legacyRendererClass,
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
                'orderDateTime' => self::DOCUMENT_DATE,
            ],
        ], $this->context);

        $legacyOperation = new DocumentGenerateOperation(
            $orderId,
            LegacyHtmlRenderer::FILE_EXTENSION,
            $this->getComparisonLegacyConfig(),
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

        $input = new RenderInput(
            documentType: $documentType->value,
            documentNumber: self::DOCUMENT_NUMBER,
            order: $order,
            data: [$dataProvider->getKey() => $this->buildRenderData($documentType, $order, true)],
        );

        $result = $this->htmlRenderer->renderToString(
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
     * @return iterable<string, array{DocumentType, class-string<AbstractDocumentDataProvider>, class-string<AbstractDocumentRenderer>}>
     */
    public static function provideLegacyDocumentTypes(): iterable
    {
        yield 'invoice' => [
            DocumentType::INVOICE,
            InvoiceDataProvider::class,
            LegacyInvoiceRenderer::class,
        ];
    }

    private function buildRenderData(
        DocumentType $documentType,
        OrderEntity $order,
        bool $withoutCompanyCountry = false,
    ): AbstractRenderData {
        $companyCountry = $withoutCompanyCountry ? new CountryEntity() : $this->companyCountry;

        /** @phpstan-ignore match.unhandled */
        return match ($documentType) {
            DocumentType::INVOICE => $this->buildInvoiceRenderData($companyCountry, $order),
        };
    }

    private function buildInvoiceRenderData(CountryEntity $companyCountry, OrderEntity $order): InvoiceRenderData
    {
        $cfg = $this->getComparisonLegacyConfig();

        $displayOptions = new DocumentDisplayOptions(
            displayHeader: $cfg['displayHeader'],
            displayFooter: $cfg['displayFooter'],
            displayPageCount: $cfg['displayPageCount'],
            displayCompanyAddress: $cfg['displayCompanyAddress'],
            displayReturnAddress: $cfg['displayReturnAddress'],
            displayLineItems: $cfg['displayLineItems'],
            displayLineItemPosition: $cfg['displayLineItemPosition'],
            displayPrices: $cfg['displayPrices'],
            displayDivergentDeliveryAddress: $cfg['displayDivergentDeliveryAddress'],
        );

        $lineItems = LineItemView::listFromOrder($order);
        $allowanceCharges = AllowanceChargeView::listFromOrder($order);

        return new InvoiceRenderData(
            config: $this->buildDocumentConfig(),
            company: $this->buildDocumentCompanyInfo($companyCountry),
            display: $displayOptions,
            documentDate: $cfg['documentDate'],
            documentNumber: $cfg['documentNumber'],
            documentComment: $cfg['documentComment'],
            templatePaths: InvoiceDataProvider::TEMPLATE_PATHS,
            typeCode: TypeCode::INVOICE,
            buyerReference: '10000',
            buyer: TradePartyView::buyerFromOrder($order),
            deliveryDate: new \DateTimeImmutable('2026-05-15T00:00:00+00:00'),
            lineItems: $lineItems,
            allowanceCharges: $allowanceCharges,
            taxBreakdown: TaxBreakdownView::listFromOrder($order),
            monetarySummation: MonetarySummationView::fromOrder($order, $lineItems, $allowanceCharges),
            paymentMeans: PaymentMeansView::fromOrder($order, $cfg['bankIban'], $cfg['bankBic']),
            paymentDueDate: new \DateTimeImmutable('2026-06-04T00:00:00+00:00'),
            intraCommunityDelivery: false,
            custom: ['invoiceNumber' => $cfg['documentNumber']],
            legacyConfig: $cfg,
        );
    }

    private function buildDocumentConfig(): DocumentConfig
    {
        $cfg = $this->getComparisonLegacyConfig();

        return new DocumentConfig(
            pageSize: $cfg['pageSize'],
            pageOrientation: $cfg['pageOrientation'],
            itemsPerPage: $cfg['itemsPerPage'],
        );
    }

    private function buildDocumentCompanyInfo(CountryEntity $companyCountry): DocumentCompanyInfo
    {
        $cfg = $this->getComparisonLegacyConfig();

        return new DocumentCompanyInfo(
            companyName: $cfg['companyName'],
            companyStreet: $cfg['companyStreet'],
            companyZipcode: $cfg['companyZipcode'],
            companyCity: $cfg['companyCity'],
            companyCountry: $companyCountry,
            companyEmail: $cfg['companyEmail'],
            companyPhone: $cfg['companyPhone'],
            companyUrl: $cfg['companyUrl'],
            executiveDirector: $cfg['executiveDirector'],
            taxNumber: $cfg['taxNumber'],
            taxOffice: $cfg['taxOffice'],
            vatId: $cfg['vatId'],
            bankName: $cfg['bankName'],
            bankIban: $cfg['bankIban'],
            bankBic: $cfg['bankBic'],
            placeOfJurisdiction: $cfg['placeOfJurisdiction'],
            placeOfFulfillment: $cfg['placeOfFulfillment'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getComparisonLegacyConfig(): array
    {
        return [
            'documentNumber' => self::DOCUMENT_NUMBER,
            'documentDate' => self::DOCUMENT_DATE,
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
            'companyStreet' => 'Example Street 1',
            'companyZipcode' => '12345',
            'companyCity' => 'Example City',
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
            'pageSize' => 'a4',
            'pageOrientation' => 'portrait',
            'itemsPerPage' => 10,
        ];
    }

    private function applyTenPercentPromotion(Cart $cart): Cart
    {
        $code = 'TENOFF';

        $this->createTestFixturePercentagePromotion(
            Uuid::randomHex(),
            $code,
            10.0,
            null,
            static::getContainer(),
        );

        $promoLineItem = (new PromotionItemBuilder())->buildPlaceholderItem($code);

        return static::getContainer()
            ->get(CartService::class)
            ->add($cart, $promoLineItem, $this->salesChannelContext);
    }

    private function loadCompanyCountry(): CountryEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        /** @var EntityRepository<CountryCollection> $repo */
        $repo = static::getContainer()->get('country.repository');
        $country = $repo
            ->search($criteria, $this->context)
            ->getEntities()
            ->first();

        static::assertInstanceOf(CountryEntity::class, $country);

        return $country;
    }
}
