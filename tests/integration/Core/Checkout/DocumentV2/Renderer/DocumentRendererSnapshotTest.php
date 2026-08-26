<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Renderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer as LegacyDeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer as LegacyInvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer as LegacyHtmlRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\CancellationInvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\CreditNoteDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\DeliveryNoteDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\CancellationInvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\CreditNoteRenderData;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DeliveryNoteRenderData;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Renderer\HtmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Renderer\ZugferdXmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\ReferencedDocument;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\DocumentV2\Template\View\AllowanceChargeView;
use Shopware\Core\Checkout\DocumentV2\Template\View\LineItemView;
use Shopware\Core\Checkout\DocumentV2\Template\View\MonetarySummationView;
use Shopware\Core\Checkout\DocumentV2\Template\View\PaymentMeansView;
use Shopware\Core\Checkout\DocumentV2\Template\View\TaxBreakdownView;
use Shopware\Core\Checkout\DocumentV2\Template\View\TradePartyView;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentRendererSnapshotTest extends TestCase
{
    use DocumentV2Trait;
    use SnapshotTesting;

    private HtmlRenderer $htmlRenderer;

    private ZugferdXmlRenderer $xmlRenderer;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private CountryEntity $companyCountry;

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
    public function testRender(DocumentType $documentType, string $dataProviderClass, bool $renderXml = true): void
    {
        $dataProvider = static::getContainer()->get($dataProviderClass);
        static::assertInstanceOf(AbstractDocumentDataProvider::class, $dataProvider);

        $cart = $this->generateDemoCartWithTaxes([19, 7]);
        $cart = $this->applyTenPercentPromotion($cart);
        $orderId = $this->persistCart($cart);
        $this->enrichOrderForRendering($orderId);

        $criteria = new Criteria([$orderId]);
        $dataProvider->enrichOrderCriteria($criteria);

        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        $input = new RenderInput(
            documentType: $documentType->value,
            documentNumber: self::DOCUMENT_NUMBER,
            order: $order,
            data: $this->buildRenderData($documentType, $order),
        );

        $htmlResult = $this->htmlRenderer->renderToString($input, new RenderState(), $this->context);
        static::assertSame(DocumentFormat::HTML->value, $htmlResult->format);

        /**
         * @var array<int, array{type: string, actual: string}> $snaps
         */
        $snaps = [
            [
                'type' => self::TYPE_HTML,
                'actual' => $htmlResult->content,
            ],
        ];

        if ($renderXml) {
            $xmlResult = $this->xmlRenderer->renderToString($input, new RenderState(), $this->context);
            static::assertSame(DocumentFormat::ZUGFERD_XML->value, $xmlResult->format);

            $snaps[] = [
                'type' => self::TYPE_XML,
                'actual' => $xmlResult->content,
            ];
        }

        $this->assertSnapshot($documentType->value, $snaps);
    }

    /**
     * @return iterable<string, array{documentType: DocumentType, dataProviderClass: class-string<AbstractDocumentDataProvider>, renderXml?: bool}>
     */
    public static function provideDocumentTypes(): iterable
    {
        yield 'invoice' => [
            'documentType' => DocumentType::INVOICE,
            'dataProviderClass' => InvoiceDataProvider::class,
        ];

        yield 'storno' => [
            'documentType' => DocumentType::CANCELLATION_INVOICE,
            'dataProviderClass' => CancellationInvoiceDataProvider::class,
        ];

        yield 'credit_note' => [
            'documentType' => DocumentType::CREDIT_NOTE,
            'dataProviderClass' => CreditNoteDataProvider::class,
        ];

        yield 'delivery_note' => [
            'documentType' => DocumentType::DELIVERY_NOTE,
            'dataProviderClass' => DeliveryNoteDataProvider::class,
            'renderXml' => false,
        ];
    }

    public function testRenderPaginated(): void
    {
        $dataProvider = static::getContainer()->get(InvoiceDataProvider::class);

        $orderId = $this->persistCart($this->generateDemoCartWithTaxes([19, 7]));
        $this->enrichOrderForRendering($orderId);

        $criteria = new Criteria([$orderId]);
        $dataProvider->enrichOrderCriteria($criteria);

        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        $input = new RenderInput(
            documentType: DocumentType::INVOICE->value,
            documentNumber: self::DOCUMENT_NUMBER,
            order: $order,
            data: $this->buildRenderData(DocumentType::INVOICE, $order, itemsPerPage: 1),
        );

        $htmlResult = $this->htmlRenderer->renderToString($input, new RenderState(), $this->context);

        $this->assertSnapshot('invoice_paginated', [
            [
                'type' => self::TYPE_HTML,
                'actual' => $htmlResult->content,
            ],
        ]);
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
        $this->enrichOrderForRendering($orderId);

        $legacyConfig = $this->getDemoInvoiceLegacyConfig();

        if ($documentType === DocumentType::DELIVERY_NOTE) {
            $legacyConfig['custom'] = [
                'deliveryDate' => self::DOCUMENT_DATE,
                'deliveryNoteDate' => self::DOCUMENT_DATE,
            ];
        }

        $legacyOperation = new DocumentGenerateOperation(
            $orderId,
            LegacyHtmlRenderer::FILE_EXTENSION,
            $legacyConfig,
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
            data: $this->buildRenderData($documentType, $order, true),
        );

        $result = $this->htmlRenderer->renderToString(
            $input,
            new RenderState(),
            $this->context,
        );

        // the media attribute and the screen rules are intentionally v2-only
        $content = (string) preg_replace(
            ['/ media="screen"/', '/\s*(?<!\w)\.(page_break|letter-body:has)[^{}]*\{[^{}]*\}/'],
            '',
            $result->content,
        );

        static::assertSame(
            self::normalizeHtml($legacyContent),
            self::normalizeHtml($content),
        );
    }

    /**
     * @return iterable<string, array{documentType:DocumentType, dataProviderClass:class-string<AbstractDocumentDataProvider>, legacyRendererClass:class-string<AbstractDocumentRenderer>}>
     */
    public static function provideLegacyDocumentTypes(): iterable
    {
        yield 'invoice' => [
            'documentType' => DocumentType::INVOICE,
            'dataProviderClass' => InvoiceDataProvider::class,
            'legacyRendererClass' => LegacyInvoiceRenderer::class,
        ];

        yield 'delivery_note' => [
            'documentType' => DocumentType::DELIVERY_NOTE,
            'dataProviderClass' => DeliveryNoteDataProvider::class,
            'legacyRendererClass' => LegacyDeliveryNoteRenderer::class,
        ];
    }

    /**
     * @return array<string, AbstractRenderData>
     */
    private function buildRenderData(
        DocumentType $documentType,
        OrderEntity $order,
        bool $withoutCompanyCountry = false,
        ?int $itemsPerPage = null,
    ): array {
        $companyCountry = $withoutCompanyCountry ? new CountryEntity() : $this->companyCountry;

        $data = [
            DocumentMetaProvider::KEY => $this->buildMeta($companyCountry, $itemsPerPage),
        ];

        $data += match ($documentType) {
            DocumentType::INVOICE => [InvoiceDataProvider::KEY => $this->buildInvoiceRenderData($order)],
            DocumentType::CANCELLATION_INVOICE => [CancellationInvoiceDataProvider::KEY => $this->buildCancellationInvoiceRenderData($order)],
            DocumentType::CREDIT_NOTE => [CreditNoteDataProvider::KEY => $this->buildCreditNoteRenderData($order)],
            DocumentType::DELIVERY_NOTE => [DeliveryNoteDataProvider::KEY => $this->buildDeliveryNoteRenderData()],
            /**
             * The app_provided sentinel is not a renderable document type, it carries no provider data.
             * @phpstan-ignore classConstant.deprecated
             */
            DocumentType::APP_PROVIDED => [],
        };

        return $data;
    }

    private function buildCreditNoteRenderData(OrderEntity $order): CreditNoteRenderData
    {
        $this->seedDemoBaseConfig('credit_note');

        $referencedInvoiceId = $this->seedReferenceInvoice($order->getId());

        $order->getLineItems()?->add($this->buildCreditLineItem());

        $provider = static::getContainer()->get(CreditNoteDataProvider::class);

        $request = new DocumentGenerationRequest(
            $order->getId(),
            DocumentType::CREDIT_NOTE,
            [DocumentFormat::ZUGFERD_XML],
            self::DOCUMENT_NUMBER,
            documentDate: self::DOCUMENT_DATE,
        );

        $input = new ProviderInput($order, $request, new ReferencedDocument(
            id: $referencedInvoiceId,
            documentNumber: self::DOCUMENT_NUMBER,
            orderVersionId: $order->getVersionId() ?? Defaults::LIVE_VERSION,
        ));

        return $provider->provideRenderingData($input, $this->context);
    }

    private function buildCreditLineItem(): OrderLineItemEntity
    {
        $credit = new OrderLineItemEntity();
        $credit->setId(Uuid::randomHex());
        $credit->setUniqueIdentifier(Uuid::randomHex());
        $credit->setType(LineItem::CREDIT_LINE_ITEM_TYPE);
        $credit->setIdentifier('credit');
        $credit->setLabel('Credit');
        $credit->setPosition(100);
        $credit->setQuantity(1);
        $credit->setUnitPrice(-40.0);
        $credit->setTotalPrice(-40.0);
        $credit->setPrice(new CalculatedPrice(
            -40.0,
            -40.0,
            new CalculatedTaxCollection([
                new CalculatedTax(-3.8, 19.0, -20.0),
                new CalculatedTax(-1.4, 7.0, -20.0),
            ]),
            new TaxRuleCollection(),
            1,
        ));

        return $credit;
    }

    private function buildCancellationInvoiceRenderData(OrderEntity $order): CancellationInvoiceRenderData
    {
        $this->seedDemoBaseConfig('storno');
        $referencedInvoiceId = $this->seedReferenceInvoice($order->getId());

        $provider = static::getContainer()->get(CancellationInvoiceDataProvider::class);

        $request = new DocumentGenerationRequest(
            $order->getId(),
            DocumentType::CANCELLATION_INVOICE,
            [DocumentFormat::ZUGFERD_XML],
            self::DOCUMENT_NUMBER,
            documentDate: self::DOCUMENT_DATE,
        );

        $input = new ProviderInput($order, $request, new ReferencedDocument(
            id: $referencedInvoiceId,
            documentNumber: self::DOCUMENT_NUMBER,
            orderVersionId: $order->getVersionId() ?? Defaults::LIVE_VERSION,
        ));

        return $provider->provideRenderingData($input, $this->context);
    }

    private function buildDeliveryNoteRenderData(): DeliveryNoteRenderData
    {
        $cfg = $this->getDemoInvoiceLegacyConfig();

        return new DeliveryNoteRenderData(
            custom: [
                'deliveryNoteNumber' => $cfg['documentNumber'],
                'deliveryDate' => $cfg['documentDate'],
                'deliveryNoteDate' => $cfg['documentDate'],
            ],
        );
    }

    private function buildMeta(
        CountryEntity $companyCountry,
        ?int $itemsPerPage = null,
    ): DocumentMetaRenderData {
        $cfg = $this->getDemoInvoiceLegacyConfig();

        return new DocumentMetaRenderData(
            config: $this->buildDocumentConfig($itemsPerPage),
            company: $this->buildDocumentCompanyInfo($companyCountry),
            display: $this->buildDisplayOptions($cfg),
            documentDate: $cfg['documentDate'],
            documentNumber: $cfg['documentNumber'],
            documentComment: $cfg['documentComment'],
            legacyConfig: $cfg,
        );
    }

    private function buildInvoiceRenderData(
        OrderEntity $order,
    ): InvoiceRenderData {
        $cfg = $this->getDemoInvoiceLegacyConfig();

        $lineItems = LineItemView::listFromOrder($order);
        $allowanceCharges = AllowanceChargeView::listFromOrder($order);

        return new InvoiceRenderData(
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
        );
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private function buildDisplayOptions(array $cfg): DocumentDisplayOptions
    {
        return new DocumentDisplayOptions(
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
    }

    private function buildDocumentConfig(?int $itemsPerPage = null): DocumentConfig
    {
        $cfg = $this->getDemoInvoiceLegacyConfig();

        return new DocumentConfig(
            pageSize: $cfg['pageSize'],
            pageOrientation: $cfg['pageOrientation'],
            itemsPerPage: $itemsPerPage ?? $cfg['itemsPerPage'],
        );
    }

    private function buildDocumentCompanyInfo(CountryEntity $companyCountry): DocumentCompanyInfo
    {
        $cfg = $this->getDemoInvoiceLegacyConfig();

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
}
