<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ProductExport\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\ThemeService;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
class ProductExportControllerTest extends TestCase
{
    use AppSystemTestBehaviour;
    use SalesChannelFunctionalTestBehaviour;

    private EntityRepository $repository;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product_export.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testInvalidData(): void
    {
        $client = $this->createSalesChannelBrowser(null, true);
        $client->request('GET', getenv('APP_URL') . '/store-api/product-export/foo/bar');

        static::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testUtf8CsvExport(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelDomainId = Uuid::randomHex();

        $client = $this->createSalesChannelBrowser(null, false, [
            'id' => $salesChannelId,
            'domains' => [
                [
                    'id' => $salesChannelDomainId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);

        $productExport = $this->createCsvExport(
            ProductExportEntity::ENCODING_UTF8,
            $salesChannelId,
            $salesChannelDomainId
        );
        $client->request('GET', getenv('APP_URL') . sprintf('/store-api/product-export/%s/%s', $productExport->getAccessKey(), $productExport->getFileName()));

        $csvRows = explode(\PHP_EOL, (string) $client->getResponse()->getContent());

        static::assertCount(4, $csvRows);
        static::assertEquals(ProductExportEntity::ENCODING_UTF8, $client->getResponse()->getCharset());
    }

    public function testUtf8CsvExportWithTheme(): void
    {
        if (!$this->getContainer()->has(ThemeService::class) || !$this->getContainer()->has('theme.repository')) {
            static::markTestSkipped('This test needs storefront to be installed.');
        }

        $themeService = $this->getContainer()->get(ThemeService::class);
        $themeRepo = $this->getContainer()->get('theme.repository');

        $salesChannelId = Uuid::randomHex();
        $salesChannelDomainId = Uuid::randomHex();

        $this->getContainer()->get(Translator::class)->reset();

        $client = $this->createSalesChannelBrowser(null, false, [
            'id' => $salesChannelId,
            'domains' => [
                [
                    'id' => $salesChannelDomainId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);

        $productExport = $this->createCsvExport(
            ProductExportEntity::ENCODING_UTF8,
            $salesChannelId,
            $salesChannelDomainId
        );

        $context = Context::createDefaultContext();
        $this->loadAppsFromDir(__DIR__ . '/fixtures/theme');
        $this->reloadAppSnippets();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'SwagTheme'));
        $themeId = $themeRepo->searchIds($criteria, $context)->firstId();
        static::assertNotNull($themeId);

        $this->getContainer()->get(Translator::class)->reset();

        $themeService->assignTheme($themeId, $salesChannelId, $context, true);

        $client->request('GET', getenv('APP_URL') . sprintf('/store-api/product-export/%s/%s', $productExport->getAccessKey(), $productExport->getFileName()));

        $csvRows = explode(\PHP_EOL, (string) $client->getResponse()->getContent());

        static::assertCount(4, $csvRows);
        static::assertEquals('SwagTheme EN Test', $csvRows[1]);
        static::assertEquals(ProductExportEntity::ENCODING_UTF8, $client->getResponse()->getCharset());

        // Switch to DE
        $deSalesChannelId = Uuid::randomHex();
        $deSalesChannelDomainId = Uuid::randomHex();

        $client = $this->createSalesChannelBrowser(null, false, [
            'id' => $deSalesChannelId,
            'languageId' => $this->getDeDeLanguageId(),
            'languages' => [
                [
                    'id' => $this->getDeDeLanguageId(),
                ],
            ],
            'domains' => [
                [
                    'id' => $deSalesChannelDomainId,
                    'languageId' => $this->getDeDeLanguageId(),
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                    'url' => 'http://example.com/de',
                ],
            ],
        ]);

        $this->getContainer()->get(Translator::class)->reset();

        $themeService->assignTheme($themeId, $deSalesChannelId, $context);
        $productExportDe = $this->createCsvExport(
            ProductExportEntity::ENCODING_UTF8,
            $deSalesChannelId,
            $deSalesChannelDomainId
        );

        $client->request('GET', getenv('APP_URL') . sprintf('/store-api/product-export/%s/%s', $productExportDe->getAccessKey(), $productExportDe->getFileName()));

        $csvRows = explode(\PHP_EOL, (string) $client->getResponse()->getContent());

        static::assertCount(4, $csvRows);
        static::assertEquals('SwagTheme DE Test', $csvRows[1]);
    }

    public function testIsoCsvExport(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelDomainId = Uuid::randomHex();

        $client = $this->createSalesChannelBrowser(null, false, [
            'id' => $salesChannelId,
            'domains' => [
                [
                    'id' => $salesChannelDomainId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);

        $productExport = $this->createCsvExport(
            ProductExportEntity::ENCODING_ISO88591,
            $salesChannelId,
            $salesChannelDomainId
        );

        $client->request('GET', getenv('APP_URL') . sprintf('/store-api/product-export/%s/%s', $productExport->getAccessKey(), $productExport->getFileName()));

        $csvRows = explode(\PHP_EOL, (string) $client->getResponse()->getContent());

        static::assertCount(4, $csvRows);
        static::assertEquals(ProductExportEntity::ENCODING_ISO88591, $client->getResponse()->getCharset());
    }

    public function testUtf8XmlExport(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelDomainId = Uuid::randomHex();

        $client = $this->createSalesChannelBrowser(null, false, [
            'id' => $salesChannelId,
            'domains' => [
                [
                    'id' => $salesChannelDomainId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);
        $productExport = $this->createXmlExport(
            ProductExportEntity::ENCODING_UTF8,
            $salesChannelId,
            $salesChannelDomainId
        );

        $client->request('GET', getenv('APP_URL') . sprintf('/store-api/product-export/%s/%s', $productExport->getAccessKey(), $productExport->getFileName()));

        static::assertEquals(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $xml = simplexml_load_string((string) $client->getResponse()->getContent());

        static::assertEquals(ProductExportEntity::ENCODING_UTF8, $client->getResponse()->getCharset());
        static::assertInstanceOf(\SimpleXMLElement::class, $xml);
        static::assertCount(2, $xml);
    }

    public function testIsoXmlExport(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelDomainId = Uuid::randomHex();

        $client = $this->createSalesChannelBrowser(null, false, [
            'id' => $salesChannelId,
            'domains' => [
                [
                    'id' => $salesChannelDomainId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);
        $productExport = $this->createXmlExport(
            ProductExportEntity::ENCODING_ISO88591,
            $salesChannelId,
            $salesChannelDomainId
        );

        $client->request('GET', sprintf('/store-api/product-export/%s/%s', $productExport->getAccessKey(), $productExport->getFileName()));

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $xml = simplexml_load_string((string) $response->getContent());

        static::assertEquals(ProductExportEntity::ENCODING_ISO88591, $response->getCharset());
        static::assertInstanceOf(\SimpleXMLElement::class, $xml);
        static::assertCount(2, $xml);
    }

    protected function getSalesChannelDomain(): SalesChannelDomainEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('sales_channel_domain.repository');

        /** @var SalesChannelDomainEntity $salesChannelDomain */
        $salesChannelDomain = $repository->search(new Criteria(), $this->context)->first();
        static::assertNotNull($salesChannelDomain);

        return $salesChannelDomain;
    }

    private function createCsvExport(string $encoding, string $salesChannelId, string $salesChannelDomainId): ProductExportEntity
    {
        $this->createProductStream($salesChannelId);

        $productExportId = Uuid::randomHex();

        $this->repository->upsert([
            [
                'id' => $productExportId,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => $encoding,
                'fileFormat' => ProductExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'headerTemplate' => 'name,url',
                'bodyTemplate' => '{{ "title"|trans }} {{ product.translated.name }}',
                'productStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'storefrontSalesChannelId' => $salesChannelId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'salesChannelDomainId' => $salesChannelDomainId,
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        /** @var ProductExportEntity $productExport */
        $productExport = $this->repository->search(new Criteria([$productExportId]), $this->context)->get($productExportId);
        static::assertNotNull($productExport);

        return $productExport;
    }

    private function createXmlExport(string $encoding, string $salesChannelId, string $salesChannelDomainId): ProductExportEntity
    {
        $this->createProductStream($salesChannelId);

        $productExportId = Uuid::randomHex();

        $this->repository->upsert([
            [
                'id' => $productExportId,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => $encoding,
                'fileFormat' => ProductExportEntity::FILE_FORMAT_XML,
                'interval' => 0,
                'headerTemplate' => '<root>',
                'bodyTemplate' => '<product><name>{{ product.name }}</name></product>',
                'footerTemplate' => '</root>',
                'productStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'storefrontSalesChannelId' => $salesChannelId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'salesChannelDomainId' => $salesChannelDomainId,
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        /** @var ProductExportEntity $productExport */
        $productExport = $this->repository->search(new Criteria([$productExportId]), $this->context)->get($productExportId);
        static::assertNotNull($productExport);

        return $productExport;
    }

    private function createProductStream(string $salesChannelId): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $randomProductIds = implode('|', \array_slice(array_column($this->createProducts($salesChannelId), 'id'), 0, 2));

        $connection->executeStatement("
            REPLACE INTO `product_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('137B079935714281BA80B40F83F8D7EB'), '[{\"type\": \"multi\", \"queries\": [{\"type\": \"multi\", \"queries\": [{\"type\": \"equalsAny\", \"field\": \"product.id\", \"value\": \"{$randomProductIds}\"}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"product.width\", \"parameters\": {\"gte\": 221, \"lte\": 932}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"product.width\", \"parameters\": {\"lte\": 245}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"equals\", \"field\": \"product.manufacturer.id\", \"value\": \"02f6b9aa385d4f40aaf573661b2cf919\"}, {\"type\": \"range\", \"field\": \"product.height\", \"parameters\": {\"gte\": 182}}], \"operator\": \"AND\"}], \"operator\": \"OR\"}]', 0, '2019-08-16 08:43:57.488', NULL);
        ");

        $connection->executeStatement("
            REPLACE INTO `product_stream_filter` (`id`, `product_stream_id`, `parent_id`, `type`, `field`, `operator`, `value`, `parameters`, `position`, `custom_fields`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), UNHEX('137B079935714281BA80B40F83F8D7EB'), NULL, 'multi', NULL, 'OR', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.469', NULL),
                (UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 1, NULL, '2019-08-16 08:43:57.478', NULL),
                (UNHEX('272B4392E7B34EF2ABB4827A33630C1D'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 3, NULL, '2019-08-16 08:43:57.486', NULL),
                (UNHEX('4A7AEB36426A482A8BFFA049F795F5E7'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.470', NULL),
                (UNHEX('BB87D86524FB4E7EA01EE548DD43A5AC'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 2, NULL, '2019-08-16 08:43:57.483', NULL),
                (UNHEX('56C5DF0B41954334A7B0CDFEDFE1D7E9'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('272B4392E7B34EF2ABB4827A33630C1D'), 'range', 'width', NULL, NULL, '{\"lte\":932,\"gte\":221}', 1, NULL, '2019-08-16 08:43:57.488', NULL),
                (UNHEX('6382E03A768F444E9C2A809C63102BD4'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('BB87D86524FB4E7EA01EE548DD43A5AC'), 'range', 'height', NULL, NULL, '{\"gte\":182}', 2, NULL, '2019-08-16 08:43:57.485', NULL),
                (UNHEX('7CBC1236ABCD43CAA697E9600BF1DF6E'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('4A7AEB36426A482A8BFFA049F795F5E7'), 'range', 'width', NULL, NULL, '{\"lte\":245}', 1, NULL, '2019-08-16 08:43:57.476', NULL),
                (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, '{$randomProductIds}', NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL);
        ");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function createProducts(string $salesChannelId): array
    {
        $productRepository = $this->getContainer()->get('product.repository');
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $products = [];

        for ($i = 0; $i < 10; ++$i) {
            $products[] = [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $taxId, 'taxRate' => 17, 'name' => 'with id'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        $productRepository->create($products, $this->context);

        return $products;
    }
}
