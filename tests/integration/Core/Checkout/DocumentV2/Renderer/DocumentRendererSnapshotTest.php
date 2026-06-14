<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Renderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer as LegacyInvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer as LegacyHtmlRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\DocumentV2\Config\CompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Renderer\HtmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
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
    use SnapshotTesting;

    private const DOCUMENT_NUMBER = '1000';

    private const DOCUMENT_DATE = '2026-05-05T12:00:00+00:00';

    private Context $context;

    private SalesChannelContext $salesChannelContext;

    private HtmlRenderer $renderer;

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

        $this->renderer = static::getContainer()->get(HtmlRenderer::class);
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

        $orderId = $this->persistCart($this->generateDemoCartWithTaxes([19, 7]));

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
            data: [$dataProvider->getKey() => $this->buildRenderData($documentType)],
        );

        $result = $this->renderer->renderToString(
            $input,
            new RenderState(),
            $this->context,
        );

        static::assertSame(DocumentFormat::HTML->value, $result->format);
        static::assertSame('html', $result->fileExtension);
        static::assertSame('text/html', $result->mimeType);

        $this->assertSnapshot($documentType->value . '_renderer', [
            [
                'type' => self::TYPE_HTML,
                'actual' => $result->content,
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
            data: [$dataProvider->getKey() => $this->buildRenderData($documentType, true)],
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

    private function buildRenderData(DocumentType $documentType, bool $withoutCompanyCountry = false): AbstractRenderData
    {
        $companyCountry = $withoutCompanyCountry ? new CountryEntity() : $this->companyCountry;

        /** @phpstan-ignore match.unhandled */
        return match ($documentType) {
            DocumentType::INVOICE => $this->buildInvoiceRenderData($companyCountry),
        };
    }

    private function buildInvoiceRenderData(CountryEntity $companyCountry): InvoiceRenderData
    {
        $cfg = $this->getComparisonLegacyConfig();

        return new InvoiceRenderData(
            config: $this->buildDocumentConfig(),
            company: $this->buildCompanyInfo($companyCountry),
            documentDate: $cfg['documentDate'],
            documentNumber: $cfg['documentNumber'],
            documentComment: $cfg['documentComment'],
            intraCommunityDelivery: false,
            displayDivergentDeliveryAddress: $cfg['displayDivergentDeliveryAddress'],
            displayLineItems: $cfg['displayLineItems'],
            displayLineItemPosition: $cfg['displayLineItemPosition'],
            displayPrices: $cfg['displayPrices'],
            deliveryCountries: [],
            legacyConfig: $cfg,
            custom: ['invoiceNumber' => $cfg['documentNumber']],
        );
    }

    private function buildDocumentConfig(): DocumentConfig
    {
        $cfg = $this->getComparisonLegacyConfig();

        return new DocumentConfig(
            pageSize: $cfg['pageSize'],
            pageOrientation: $cfg['pageOrientation'],
            itemsPerPage: $cfg['itemsPerPage'],
            displayHeader: $cfg['displayHeader'],
            displayFooter: $cfg['displayFooter'],
            displayPageCount: $cfg['displayPageCount'],
            displayCompanyAddress: $cfg['displayCompanyAddress'],
            displayReturnAddress: $cfg['displayReturnAddress'],
        );
    }

    private function buildCompanyInfo(CountryEntity $companyCountry): CompanyInfo
    {
        $cfg = $this->getComparisonLegacyConfig();

        return new CompanyInfo(
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
