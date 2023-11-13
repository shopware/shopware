<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ProductExport\Service;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\ProductExport\Exception\ExportNotFoundException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Service\ProductExporter;
use Shopware\Core\Content\ProductExport\Service\ProductExporterInterface;
use Shopware\Core\Content\ProductExport\Service\ProductExportFileHandler;
use Shopware\Core\Content\ProductExport\Service\ProductExportGenerator;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
class ProductExporterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $repository;

    private Context $context;

    private SalesChannelContext $salesChannelContext;

    private ProductExporterInterface $service;

    private FilesystemOperator $fileSystem;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product_export.repository');
        $this->service = $this->getContainer()->get(ProductExporter::class);
        $this->context = Context::createDefaultContext();
        $this->fileSystem = $this->getContainer()->get('shopware.filesystem.private');

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), $this->getSalesChannelDomain()->getSalesChannelId());
    }

    public function testExport(): void
    {
        $this->createTestEntity();

        $this->service->export($this->salesChannelContext, new ExportBehavior());

        $filePath = sprintf('%s/Testexport.csv', $this->getContainer()->getParameter('product_export.directory'));
        $fileContent = $this->fileSystem->read($filePath);

        static::assertIsString($fileContent);
        $csvRows = explode(\PHP_EOL, $fileContent);

        static::assertTrue($this->fileSystem->directoryExists($this->getContainer()->getParameter('product_export.directory')));
        static::assertTrue($this->fileSystem->fileExists($filePath));
        static::assertCount(4, $csvRows);
    }

    public function testExportPagination(): void
    {
        $this->createTestEntity();

        $service = new ProductExporter(
            $this->getContainer()->get('product_export.repository'),
            $this->getContainer()->get(ProductExportGenerator::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(ProductExportFileHandler::class)
        );

        $service->export($this->salesChannelContext, new ExportBehavior());

        $filePath = sprintf('%s/Testexport.csv', $this->getContainer()->getParameter('product_export.directory'));

        static::assertTrue($this->fileSystem->directoryExists($this->getContainer()->getParameter('product_export.directory')));
        static::assertTrue($this->fileSystem->fileExists($filePath));
        $fileContent = $this->fileSystem->read($filePath);
        static::assertIsString($fileContent);
        static::assertCount(4, explode(\PHP_EOL, $fileContent));
    }

    public function testExportNotFound(): void
    {
        static::expectException(ExportNotFoundException::class);
        static::expectExceptionMessage('No product exports found');

        $this->service->export($this->salesChannelContext, new ExportBehavior());
    }

    public function testExportIdNotFound(): void
    {
        $id = Uuid::randomHex();

        static::expectException(ExportNotFoundException::class);
        static::expectExceptionMessage(sprintf('Product export with ID %s not found', $id));

        $this->service->export($this->salesChannelContext, new ExportBehavior(), $id);
    }

    private function getSalesChannelId(): string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('sales_channel.repository');

        return $repository->search(new Criteria(), $this->context)->first()->getId();
    }

    private function getSalesChannelDomain(): SalesChannelDomainEntity
    {
        /** @var EntityRepository $repository */
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
                'headerTemplate' => 'name,url',
                'bodyTemplate' => '{{ product.name }}',
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
     * @return list<array<string, mixed>>
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
}
