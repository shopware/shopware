<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Renderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
class HtmlRendererTest extends TestCase
{
    use DocumentTrait;
    use SnapshotTesting;

    private Context $context;

    private SalesChannelContext $salesChannelContext;

    private HtmlRenderer $renderer;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
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

        $this->context = $this->salesChannelContext->getContext();

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
     * @param \Closure(DocumentConfig, CompanyInfo, string $documentNumber, string $documentDate): AbstractRenderData $renderDataFactory
     */
    #[DataProvider('provideHtmlDocumentTypes')]
    public function testRender(
        DocumentType $documentType,
        string $dataProviderClass,
        \Closure $renderDataFactory,
    ): void {
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

        $documentNumber = '1000';
        $documentDate = '2026-05-05T12:00:00+00:00';

        $renderData = $renderDataFactory(
            $this->buildDocumentConfig(),
            $this->buildCompanyInfo(),
            $documentNumber,
            $documentDate,
        );

        $input = new RenderInput(
            documentType: $documentType->value,
            documentNumber: $documentNumber,
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
     * @return iterable<string, array{DocumentType, class-string<AbstractDocumentDataProvider>, \Closure(DocumentConfig, CompanyInfo, string, string): AbstractRenderData}>
     */
    public static function provideHtmlDocumentTypes(): iterable
    {
        yield 'invoice' => [
            DocumentType::INVOICE,
            InvoiceDataProvider::class,
            static fn (DocumentConfig $config, CompanyInfo $company, string $documentNumber, string $documentDate): InvoiceRenderData => new InvoiceRenderData(
                $config,
                $company,
                documentDate: $documentDate,
                documentNumber: $documentNumber,
                documentComment: 'comment.',
                intraCommunityDelivery: false,
                displayDivergentDeliveryAddress: true,
                displayLineItems: true,
                displayLineItemPosition: true,
                displayPrices: true,
                deliveryCountries: [],
                legacyConfig: [],
                custom: ['invoiceNumber' => $documentNumber],
            ),
        ];
    }

    private function buildDocumentConfig(): DocumentConfig
    {
        return new DocumentConfig(
            pageSize: 'a4',
            pageOrientation: 'portrait',
            itemsPerPage: 10,
            displayHeader: true,
            displayFooter: true,
            displayPageCount: true,
            displayCompanyAddress: true,
            displayReturnAddress: true,
        );
    }

    private function buildCompanyInfo(): CompanyInfo
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

        return new CompanyInfo(
            companyName: 'Example Company',
            companyStreet: 'Example Street 1',
            companyZipcode: '12345',
            companyCity: 'Example City',
            companyCountry: $country,
            companyEmail: 'info@example.com',
            companyPhone: '+49 555 12345',
            companyUrl: 'https://example.com',
            executiveDirector: 'Jane Doe',
            taxNumber: 'DE123456789',
            taxOffice: 'Example Tax Office',
            vatId: 'DE987654321',
            bankName: 'Example Bank',
            bankIban: 'DE89370400440532013000',
            bankBic: 'COBADEFFXXX',
            placeOfJurisdiction: 'Example Place',
            placeOfFulfillment: 'Example Place',
        );
    }
}
