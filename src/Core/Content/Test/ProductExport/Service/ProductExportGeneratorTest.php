<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ProductExport\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\ProductExport\Event\ProductExportChangeEncodingEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportProductCriteriaEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderBodyContextEvent;
use Shopware\Core\Content\ProductExport\Exception\EmptyExportException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Service\ProductExportGenerator;
use Shopware\Core\Content\ProductExport\Service\ProductExportGeneratorInterface;
use Shopware\Core\Content\ProductExport\Service\ProductExportRenderer;
use Shopware\Core\Content\ProductExport\Service\ProductExportValidator;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductExportGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var ProductExportGeneratorInterface
     */
    private $service;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product_export.repository');
        $this->service = $this->getContainer()->get(ProductExportGenerator::class);
        $this->context = Context::createDefaultContext();

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), $this->getSalesChannelDomain()->getSalesChannelId());
    }

    public function testExport(): void
    {
        $productExportId = $this->createTestEntity();

        $criteria = new Criteria([$productExportId]);
        $criteria->addAssociation('salesChannelDomain.language');
        $criteria->addAssociation('salesChannel');

        $productExport = $this->repository->search($criteria, $this->context)->first();

        $exportResult = $this->service->generate($productExport, new ExportBehavior());

        static::assertStringEqualsFile(__DIR__ . '/fixtures/test-export.csv', $exportResult->getContent());
    }

    public function testProductExportGenerationEvents(): void
    {
        $productExportId = $this->createTestEntity();

        $criteria = new Criteria([$productExportId]);
        $criteria->addAssociation('salesChannelDomain.language');
        $criteria->addAssociation('salesChannel');

        $productExport = $this->repository->search($criteria, $this->context)->first();

        $exportBehavior = new ExportBehavior();

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $productExportProductCriteriaEventDispatched = false;
        $productExportProductCriteriaCallback = function () use (
            &$productExportProductCriteriaEventDispatched
        ): void {
            $productExportProductCriteriaEventDispatched = true;
        };
        $eventDispatcher->addListener(
            ProductExportProductCriteriaEvent::class,
            $productExportProductCriteriaCallback
        );

        $productExportRenderBodyContextEventDispatched = false;
        $productExportRenderBodyContextCallback = function () use (
            &$productExportRenderBodyContextEventDispatched
        ): void {
            $productExportRenderBodyContextEventDispatched = true;
        };
        $eventDispatcher->addListener(
            ProductExportRenderBodyContextEvent::class,
            $productExportRenderBodyContextCallback
        );

        $productExportChangeEncodingEventDispatched = false;
        $productExportChangeEncodingCallback = function () use (
            &$productExportChangeEncodingEventDispatched
        ): void {
            $productExportChangeEncodingEventDispatched = true;
        };
        $eventDispatcher->addListener(
            ProductExportChangeEncodingEvent::class,
            $productExportChangeEncodingCallback
        );

        $exportGenerator = new ProductExportGenerator(
            $this->getContainer()->get(ProductStreamBuilder::class),
            $this->getContainer()->get('sales_channel.product.repository'),
            $this->getContainer()->get(ProductExportRenderer::class),
            $eventDispatcher,
            $this->getContainer()->get(ProductExportValidator::class),
            $this->getContainer()->get(SalesChannelContextService::class),
            $this->getContainer()->get(Translator::class),
            $this->getContainer()->get(SalesChannelContextPersister::class),
            $this->getContainer()->get(Connection::class),
            100
        );

        $exportGenerator->generate($productExport, $exportBehavior);

        static::assertTrue($productExportProductCriteriaEventDispatched, 'ProductExportProductCriteriaEvent was not dispatched');
        static::assertTrue($productExportRenderBodyContextEventDispatched, 'ProductExportRenderBodyContextEvent was not dispatched');
        static::assertTrue($productExportChangeEncodingEventDispatched, 'ProductExportChangeEncodingEvent was not dispatched');

        $eventDispatcher->removeListener(ProductExportProductCriteriaEvent::class, $productExportProductCriteriaCallback);
        $eventDispatcher->removeListener(ProductExportRenderBodyContextEvent::class, $productExportRenderBodyContextCallback);
        $eventDispatcher->removeListener(ProductExportChangeEncodingEvent::class, $productExportChangeEncodingCallback);
    }

    public function testEmptyProductExportGenerationEvents(): void
    {
        $productExportId = $this->createTestEntity();

        $criteria = new Criteria([$productExportId]);
        $criteria->addAssociation('salesChannelDomain.language');
        $criteria->addAssociation('salesChannel');

        $productExport = $this->repository->search($criteria, $this->context)->first();

        $exportBehavior = new ExportBehavior();

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $productExportProductCriteriaEventDispatched = false;
        $productExportProductCriteriaCallback = function (ProductExportProductCriteriaEvent $event) use (
            &$productExportProductCriteriaEventDispatched
        ): void {
            $productExportProductCriteriaEventDispatched = true;
            // Change filters to guarantee empty export for this test
            $event->getCriteria()->addFilter(new EqualsFilter('active', true));
            $event->getCriteria()->addFilter(new EqualsFilter('active', false));
        };
        $eventDispatcher->addListener(
            ProductExportProductCriteriaEvent::class,
            $productExportProductCriteriaCallback
        );

        $productExportLoggingEventDispatched = false;
        $productExportLoggingCallback = function () use (
            &$productExportLoggingEventDispatched
        ): void {
            $productExportLoggingEventDispatched = true;
        };
        $eventDispatcher->addListener(
            ProductExportLoggingEvent::class,
            $productExportLoggingCallback
        );

        $exportGenerator = new ProductExportGenerator(
            $this->getContainer()->get(ProductStreamBuilder::class),
            $this->getContainer()->get('sales_channel.product.repository'),
            $this->getContainer()->get(ProductExportRenderer::class),
            $eventDispatcher,
            $this->getContainer()->get(ProductExportValidator::class),
            $this->getContainer()->get(SalesChannelContextService::class),
            $this->getContainer()->get(Translator::class),
            $this->getContainer()->get(SalesChannelContextPersister::class),
            $this->getContainer()->get(Connection::class),
            100
        );

        try {
            $exportGenerator->generate($productExport, $exportBehavior);
        } catch (EmptyExportException $emptyExportException) {
        }

        static::assertTrue($productExportProductCriteriaEventDispatched, 'ProductExportProductCriteriaEvent was not dispatched');
        static::assertTrue($productExportLoggingEventDispatched, 'ProductExportLoggingEvent was not dispatched');

        $eventDispatcher->removeListener(ProductExportLoggingEvent::class, $productExportLoggingCallback);
        $eventDispatcher->removeListener(ProductExportProductCriteriaEvent::class, $productExportProductCriteriaCallback);
    }

    private function getSalesChannelId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('sales_channel.repository');

        return $repository->search(new Criteria(), $this->context)->first()->getId();
    }

    private function getSalesChannelDomain(): SalesChannelDomainEntity
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('sales_channel_domain.repository');

        return $repository->search(new Criteria(), $this->context)->first();
    }

    private function getSalesChannelDomainId(): string
    {
        return $this->getSalesChannelDomain()->getId();
    }

    private function createTestEntity(): string
    {
        $this->createProductStream();

        $id = Uuid::randomHex();
        $this->repository->upsert([
            [
                'id' => $id,
                'fileName' => 'Testexport.csv',
                'accessKey' => Uuid::randomHex(),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'fileFormat' => ProductExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'headerTemplate' => 'name,stock',
                'bodyTemplate' => '{{ product.name }},{{ product.stock }}',
                'productStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'storefrontSalesChannelId' => $this->getSalesChannelDomain()->getSalesChannelId(),
                'salesChannelId' => $this->getSalesChannelId(),
                'salesChannelDomainId' => $this->getSalesChannelDomainId(),
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        return $id;
    }

    private function createProductStream(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $randomProductIds = implode('|', \array_slice(array_column($this->createProducts(), 'id'), 0, 2));

        $connection->exec("
            INSERT INTO `product_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('137B079935714281BA80B40F83F8D7EB'), '[{\"type\": \"multi\", \"queries\": [{\"type\": \"multi\", \"queries\": [{\"type\": \"equalsAny\", \"field\": \"product.id\", \"value\": \"{$randomProductIds}\"}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"product.width\", \"parameters\": {\"gte\": 221, \"lte\": 932}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"product.width\", \"parameters\": {\"lte\": 245}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"equals\", \"field\": \"product.manufacturer.id\", \"value\": \"02f6b9aa385d4f40aaf573661b2cf919\"}, {\"type\": \"range\", \"field\": \"product.height\", \"parameters\": {\"gte\": 182}}], \"operator\": \"AND\"}], \"operator\": \"OR\"}]', 0, '2019-08-16 08:43:57.488', NULL);
        ");

        $connection->exec("
            INSERT INTO `product_stream_filter` (`id`, `product_stream_id`, `parent_id`, `type`, `field`, `operator`, `value`, `parameters`, `position`, `custom_fields`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), UNHEX('137B079935714281BA80B40F83F8D7EB'), NULL, 'multi', NULL, 'OR', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.469', NULL),
                (UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 1, NULL, '2019-08-16 08:43:57.478', NULL),
                (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, '{$randomProductIds}', NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL);
    ");
    }

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
}
