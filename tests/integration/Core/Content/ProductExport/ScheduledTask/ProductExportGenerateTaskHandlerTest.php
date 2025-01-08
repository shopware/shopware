<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ProductExport\ScheduledTask;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTaskHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

/**
 * @internal
 */
#[Group('slow')]
class ProductExportGenerateTaskHandlerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var EntityRepository<ProductExportCollection>
     */
    private EntityRepository $productExportRepository;

    private Context $context;

    private FilesystemOperator $fileSystem;

    protected function setUp(): void
    {
        if (!static::getContainer()->has(ProductPageSeoUrlRoute::class)) {
            static::markTestSkipped('ProductExport tests need storefront bundle to be active');
        }

        $this->productExportRepository = static::getContainer()->get('product_export.repository');
        $this->context = Context::createDefaultContext();
        $this->fileSystem = static::getContainer()->get('shopware.filesystem.private');
    }

    public function testSkipGenerateByCronjobFalseProductExports(): void
    {
        $this->createProductStream();
        // only get seconds, not microseconds, for better comparison to DB
        $previousGeneratedAt = \DateTime::createFromFormat('U', (string) time());
        static::assertInstanceOf(\DateTimeInterface::class, $previousGeneratedAt);
        $exportId = $this->createTestEntity($previousGeneratedAt, 0, 'Testexport.csv', false);
        $this->clearQueue();
        $this->getTaskHandler()->run();

        $url = '/api/_action/message-queue/consume';
        $client = $this->getBrowser();
        $client->request('POST', $url, ['receiver' => 'async']);

        static::assertSame(200, $client->getResponse()->getStatusCode());
        $response = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('handledMessages', $response);
        static::assertIsInt($response['handledMessages']);
        static::assertSame(0, $response['handledMessages']);

        $filePath = \sprintf('%s/Testexport.csv', static::getContainer()->getParameter('product_export.directory'));
        static::assertFalse($this->fileSystem->fileExists($filePath));

        $newExport = $this->productExportRepository->search(new Criteria([$exportId]), $this->context)->getEntities()->first();
        static::assertNotNull($newExport);
        static::assertEquals($previousGeneratedAt, $newExport->getGeneratedAt());
    }

    protected function createSecondStorefrontSalesChannel(): void
    {
        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $criteria = (new Criteria())
            ->setIds([$this->getSalesChannelDomain()->getSalesChannelId()])
            ->addAssociation('languages');

        $originalSalesChannel = $salesChannelRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($originalSalesChannel);

        $originalSalesChannelLanguages = $originalSalesChannel->getLanguages();
        static::assertNotNull($originalSalesChannelLanguages);
        $originalSalesChannelArray = $originalSalesChannelLanguages->jsonSerialize();
        $languages = array_map(static fn (LanguageEntity $language): array => ['id' => $language->getId()], $originalSalesChannelArray);

        $id = '000000009276457086da48d5b5628f3c';
        $data = [
            'id' => $id,
            'accessKey' => $id,
            'name' => 'A totally fake Storefront SalesChannel',
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'currencyId' => Defaults::CURRENCY,
            'languages' => $languages,
            'languageId' => $originalSalesChannel->getLanguageId(),
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $this->getValidCountryId(),
        ];
        $salesChannelRepository->create([$data], $this->context);
    }

    private function getTaskHandler(): ProductExportGenerateTaskHandler
    {
        return new ProductExportGenerateTaskHandler(
            static::getContainer()->get('scheduled_task.repository'),
            $this->createMock(LoggerInterface::class),
            static::getContainer()->get(SalesChannelContextFactory::class),
            static::getContainer()->get('sales_channel.repository'),
            static::getContainer()->get('product_export.repository'),
            static::getContainer()->get('messenger.bus.shopware')
        );
    }

    private function getSalesChannelId(): string
    {
        /** @var EntityRepository<SalesChannelCollection> $repository */
        $repository = static::getContainer()->get('sales_channel.repository');

        $id = $repository->search(new Criteria(), $this->context)->getEntities()->first()?->getId();
        static::assertIsString($id);

        return $id;
    }

    private function getSalesChannelDomain(): SalesChannelDomainEntity
    {
        /** @var EntityRepository<SalesChannelDomainCollection> $repository */
        $repository = static::getContainer()->get('sales_channel_domain.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('salesChannel');
        $criteria->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $domainEntity = $repository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($domainEntity);

        return $domainEntity;
    }

    private function getSalesChannelDomainId(): string
    {
        return $this->getSalesChannelDomain()->getId();
    }

    private function createTestEntity(?\DateTimeInterface $generatedAt = null, int $interval = 0, string $filename = 'Testexport.csv', bool $generateByCronjob = true): string
    {
        $id = Uuid::randomHex();
        $this->productExportRepository->upsert([
            [
                'id' => $id,
                'fileName' => $filename,
                'accessKey' => Uuid::randomHex(),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'fileFormat' => ProductExportEntity::FILE_FORMAT_CSV,
                'interval' => $interval,
                'headerTemplate' => 'name,url',
                'bodyTemplate' => '{{ product.name }}',
                'productStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'storefrontSalesChannelId' => $this->getSalesChannelDomain()->getSalesChannelId(),
                'salesChannelId' => $this->getSalesChannelId(),
                'salesChannelDomainId' => $this->getSalesChannelDomainId(),
                'generatedAt' => $generatedAt,
                'generateByCronjob' => $generateByCronjob,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        return $id;
    }

    private function createProductStream(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $randomProductIds = implode('|', \array_slice(array_column($this->createProducts(), 'id'), 0, 2));

        $connection->executeStatement("
            INSERT INTO `product_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('137B079935714281BA80B40F83F8D7EB'), '[{\"type\": \"multi\", \"queries\": [{\"type\": \"multi\", \"queries\": [{\"type\": \"equalsAny\", \"field\": \"product.id\", \"value\": \"$randomProductIds\"}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"product.width\", \"parameters\": {\"gte\": 221, \"lte\": 932}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"product.width\", \"parameters\": {\"lte\": 245}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"equals\", \"field\": \"product.manufacturer.id\", \"value\": \"02f6b9aa385d4f40aaf573661b2cf919\"}, {\"type\": \"range\", \"field\": \"product.height\", \"parameters\": {\"gte\": 182}}], \"operator\": \"AND\"}], \"operator\": \"OR\"}]', 0, '2019-08-16 08:43:57.488', NULL);
        ");

        $connection->executeStatement("
            INSERT INTO `product_stream_filter` (`id`, `product_stream_id`, `parent_id`, `type`, `field`, `operator`, `value`, `parameters`, `position`, `custom_fields`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), UNHEX('137B079935714281BA80B40F83F8D7EB'), NULL, 'multi', NULL, 'OR', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.469', NULL),
                (UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 1, NULL, '2019-08-16 08:43:57.478', NULL),
                (UNHEX('272B4392E7B34EF2ABB4827A33630C1D'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 3, NULL, '2019-08-16 08:43:57.486', NULL),
                (UNHEX('4A7AEB36426A482A8BFFA049F795F5E7'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.470', NULL),
                (UNHEX('BB87D86524FB4E7EA01EE548DD43A5AC'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 2, NULL, '2019-08-16 08:43:57.483', NULL),
                (UNHEX('56C5DF0B41954334A7B0CDFEDFE1D7E9'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('272B4392E7B34EF2ABB4827A33630C1D'), 'range', 'width', NULL, NULL, '{\"lte\":932,\"gte\":221}', 1, NULL, '2019-08-16 08:43:57.488', NULL),
                (UNHEX('6382E03A768F444E9C2A809C63102BD4'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('BB87D86524FB4E7EA01EE548DD43A5AC'), 'range', 'height', NULL, NULL, '{\"gte\":182}', 2, NULL, '2019-08-16 08:43:57.485', NULL),
                (UNHEX('7CBC1236ABCD43CAA697E9600BF1DF6E'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('4A7AEB36426A482A8BFFA049F795F5E7'), 'range', 'width', NULL, NULL, '{\"lte\":245}', 1, NULL, '2019-08-16 08:43:57.476', NULL),
                (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, '$randomProductIds', NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL);
    ");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function createProducts(): array
    {
        $productRepository = static::getContainer()->get('product.repository');
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $salesChannelId = $this->getSalesChannelDomain()->getSalesChannelId();
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
