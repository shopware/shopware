<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ProductExport\ScheduledTask;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
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
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 *
 * @group slow
 */
class ProductExportGenerateTaskHandlerTest extends TestCase
{
    use QueueTestBehaviour;
    use AdminFunctionalTestBehaviour;

    private EntityRepository $productExportRepository;

    private Context $context;

    private FilesystemOperator $fileSystem;

    protected function setUp(): void
    {
        if (!$this->getContainer()->has(ProductPageSeoUrlRoute::class)) {
            static::markTestSkipped('ProductExport tests need storefront bundle to be active');
        }

        $this->productExportRepository = $this->getContainer()->get('product_export.repository');
        $this->context = Context::createDefaultContext();
        $this->fileSystem = $this->getContainer()->get('shopware.filesystem.private');
    }

    /**
     * @group quarantined
     */
    public function testRun(): void
    {
        // Add a second storefront sales channel, to check if all sales channels will be recognized for the product export
        $this->createSecondStorefrontSalesChannel();
        $this->createProductStream();

        // only get seconds, not microseconds, for better comparison to DB
        /** @var \DateTime $previousGeneratedAt */
        $previousGeneratedAt = \DateTime::createFromFormat('U', (string) time());
        $exportId = $this->createTestEntity($previousGeneratedAt);
        $this->clearQueue();
        $this->getTaskHandler()->run();

        $url = '/api/_action/message-queue/consume';
        $client = $this->getBrowser();
        $client->request('POST', $url, ['receiver' => 'async']);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $response = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('handledMessages', $response);
        static::assertIsInt($response['handledMessages']);
        static::assertEquals(1, $response['handledMessages']);

        $filePath = sprintf('%s/Testexport.csv', $this->getContainer()->getParameter('product_export.directory'));
        $fileContent = $this->fileSystem->read($filePath);

        $csvRows = explode(\PHP_EOL, (string) $fileContent);

        static::assertTrue($this->fileSystem->directoryExists($this->getContainer()->getParameter('product_export.directory')));
        static::assertTrue($this->fileSystem->fileExists($filePath));
        static::assertCount(4, $csvRows);

        /** @var ProductExportEntity|null $newExport */
        $newExport = $this->productExportRepository->search(new Criteria([$exportId]), $this->context)->first();
        static::assertNotNull($newExport);
        static::assertGreaterThan($previousGeneratedAt, $newExport->getGeneratedAt());
    }

    /**
     * @group quarantined
     */
    public function testSkipGenerateByCronjobFalseProductExports(): void
    {
        $this->createProductStream();
        // only get seconds, not microseconds, for better comparison to DB
        /** @var \DateTime $previousGeneratedAt */
        $previousGeneratedAt = \DateTime::createFromFormat('U', (string) time());
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
        static::assertEquals(0, $response['handledMessages']);

        $filePath = sprintf('%s/Testexport.csv', $this->getContainer()->getParameter('product_export.directory'));
        static::assertFalse($this->fileSystem->fileExists($filePath));

        /** @var ProductExportEntity|null $newExport */
        $newExport = $this->productExportRepository->search(new Criteria([$exportId]), $this->context)->first();
        static::assertNotNull($newExport);
        static::assertEquals($previousGeneratedAt, $newExport->getGeneratedAt());
    }

    /**
     * @group quarantined
     */
    public function testGeneratedAtAndIntervalsAreRespected(): void
    {
        $this->createProductStream();
        $this->clearProductExports();

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // Create one ProductExport, last exported 30 days ago, to be exported every hour
        $this->createTestEntity($now->sub(new \DateInterval('P30M')), 3600, 'Testexport.csv');

        // Create a second ProductExport, last exported 30 minutes ago, to be exported every hour
        $this->createTestEntity($now->sub(new \DateInterval('PT30M')), 3600, 'Testexport1.csv');

        /** @var TraceableMessageBus $bus */
        $bus = $this->getContainer()->get('messenger.bus.shopware');
        static::assertInstanceOf(TraceableMessageBus::class, $bus);

        $this->clearQueue();
        // Since clearing the queue doesn't seem to really work, check difference in message number
        $messagesBefore = $this->getDispatchedMessages();
        $this->getTaskHandler()->run();
        $messagesAfter = $this->getDispatchedMessages();

        static::assertCount(\count($messagesBefore) + 1, $messagesAfter);
    }

    /**
     * @group quarantined
     */
    public function testGeneratedAtIsNullWorks(): void
    {
        $this->createProductStream();
        $this->clearProductExports();

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // Create one ProductExport, last exported 30 days ago, to be exported every hour
        $this->createTestEntity(null, 3600, 'Testexport.csv');

        // Create a second ProductExport, last exported 30 minutes ago, to be exported every hour
        $this->createTestEntity($now->sub(new \DateInterval('PT30M')), 3600, 'Testexport1.csv');

        /** @var TraceableMessageBus $bus */
        $bus = $this->getContainer()->get('messenger.bus.shopware');
        static::assertInstanceOf(TraceableMessageBus::class, $bus);

        $this->clearQueue();
        // Since clearing the queue doesn't seem to really work, check difference in message number
        $messagesBefore = $bus->getDispatchedMessages();
        $this->getTaskHandler()->run();
        $messagesAfter = $bus->getDispatchedMessages();

        static::assertCount(\count($messagesBefore) + 1, $messagesAfter);
    }

    /**
     * @group quarantined
     */
    public function testSchedulerRunIfSalesChannelIsActive(): void
    {
        $this->prepareProductExportForScheduler(true);

        /** @var TraceableMessageBus $bus */
        $bus = $this->getContainer()->get('messenger.bus.shopware');
        static::assertInstanceOf(TraceableMessageBus::class, $bus);

        $this->clearQueue();
        // Since clearing the queue doesn't seem to really work, check difference in message number
        $messagesBefore = $bus->getDispatchedMessages();
        $this->getTaskHandler()->run();
        $messagesAfter = $bus->getDispatchedMessages();

        static::assertCount(\count($messagesBefore) + 1, $messagesAfter);
    }

    /**
     * @group quarantined
     */
    public function testSchedulerDontRunIfSalesChannelIsNotActive(): void
    {
        $this->prepareProductExportForScheduler(false);

        /** @var TraceableMessageBus $bus */
        $bus = $this->getContainer()->get('messenger.bus.shopware');
        static::assertInstanceOf(TraceableMessageBus::class, $bus);

        $this->clearQueue();
        // Since clearing the queue doesn't seem to really work, check difference in message number
        $messagesBefore = $bus->getDispatchedMessages();
        $this->getTaskHandler()->run();
        $messagesAfter = $bus->getDispatchedMessages();

        static::assertCount(\count($messagesBefore), $messagesAfter);
    }

    protected function createSecondStorefrontSalesChannel(): void
    {
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $criteria = (new Criteria())
            ->setIds([$this->getSalesChannelDomain()->getSalesChannelId()])
            ->addAssociation('languages');

        /** @var SalesChannelEntity $originalSalesChannel */
        $originalSalesChannel = $salesChannelRepository->search($criteria, $this->context)->first();

        /** @var LanguageCollection $originalSalesChannelLanguages */
        $originalSalesChannelLanguages = $originalSalesChannel->getLanguages();
        $originalSalesChannelArray = $originalSalesChannelLanguages->jsonSerialize();
        $languages = array_map(static fn ($language) => ['id' => $language->getId()], $originalSalesChannelArray);

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
            $this->getContainer()->get('scheduled_task.repository'),
            $this->getContainer()->get(SalesChannelContextFactory::class),
            $this->getContainer()->get('sales_channel.repository'),
            $this->getContainer()->get('product_export.repository'),
            $this->getContainer()->get('messenger.bus.shopware')
        );
    }

    private function getSalesChannelId(): string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('sales_channel.repository');

        return $repository->search(new Criteria(), $this->context)->first()->getId();
    }

    private function getSalesChannelDomain(): SalesChannelDomainEntity
    {
        $repository = $this->getContainer()->get('sales_channel_domain.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('salesChannel');
        $criteria->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        return $repository->search($criteria, $this->context)->first();
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

    /**
     * @throws Exception
     */
    private function createProductStream(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $randomProductIds = implode('|', \array_slice(array_column($this->createProducts(), 'id'), 0, 2));

        $connection->executeStatement("
            INSERT INTO `product_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('137B079935714281BA80B40F83F8D7EB'), '[{\"type\": \"multi\", \"queries\": [{\"type\": \"multi\", \"queries\": [{\"type\": \"equalsAny\", \"field\": \"product.id\", \"value\": \"{$randomProductIds}\"}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"product.width\", \"parameters\": {\"gte\": 221, \"lte\": 932}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"product.width\", \"parameters\": {\"lte\": 245}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"equals\", \"field\": \"product.manufacturer.id\", \"value\": \"02f6b9aa385d4f40aaf573661b2cf919\"}, {\"type\": \"range\", \"field\": \"product.height\", \"parameters\": {\"gte\": 182}}], \"operator\": \"AND\"}], \"operator\": \"OR\"}]', 0, '2019-08-16 08:43:57.488', NULL);
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
                (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, '{$randomProductIds}', NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL);
    ");
    }

    /**
     * @return array<mixed>
     */
    private function createProducts(): array
    {
        $productRepository = $this->getContainer()->get('product.repository');
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

    private function clearProductExports(): void
    {
        /** @var list<string> $ids */
        $ids = $this->productExportRepository->searchIds(new Criteria(), $this->context)->getIds();

        $ids = array_map(fn ($id) => ['id' => $id], $ids);

        $this->productExportRepository->delete($ids, $this->context);
    }

    private function prepareProductExportForScheduler(bool $active): void
    {
        $this->createProductStream();
        $this->clearProductExports();
        $this->createTestEntity();

        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->getSalesChannelId()));
        $salesChannelId = $salesChannelRepository->searchIds($criteria, $this->context)->firstId();

        $salesChannelRepository->update([
            [
                'id' => $salesChannelId,
                'active' => $active,
            ],
        ], $this->context);
    }

    /**
     * @return list<object>
     */
    private function getDispatchedMessages(): array
    {
        /** @var TraceableMessageBus $bus */
        $bus = $this->getContainer()->get('messenger.bus.shopware');

        return $bus->getDispatchedMessages();
    }
}
