<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ProductExport\EventListener;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @internal
 */
#[Group('slow')]
class ProductExportEventListenerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var EntityRepository<ProductExportCollection>
     */
    private EntityRepository $productExportRepository;

    private Context $context;

    private FilesystemOperator $fileSystem;

    protected function setUp(): void
    {
        $this->productExportRepository = static::getContainer()->get('product_export.repository');
        $this->context = Context::createDefaultContext();
        $this->fileSystem = static::getContainer()->get('shopware.filesystem.private');
        $this->ensureProductStream();
    }

    public function testEditResetsIsRunningAndDeletesFile(): void
    {
        $exportId = $this->createTestEntity('EventListenerTest.csv');

        // Create an existing export file
        $filePath = \sprintf('%s/%s', static::getContainer()->getParameter('product_export.directory'), 'EventListenerTest.csv');
        $this->fileSystem->write($filePath, 'dummy');
        static::assertTrue($this->fileSystem->fileExists($filePath));

        // Mark as running to simulate stuck state
        $this->productExportRepository->update([[
            'id' => $exportId,
            'isRunning' => true,
        ]], $this->context);

        // Update an unrelated property to trigger the listener
        $this->productExportRepository->update([[
            'id' => $exportId,
            'interval' => 999,
        ]], $this->context);

        // Assert file deleted and flags reset
        static::assertFalse($this->fileSystem->fileExists($filePath));

        $entity = $this->productExportRepository->search(new Criteria([$exportId]), $this->context)->getEntities()->first();
        static::assertNotNull($entity);
        static::assertFalse($entity->getIsRunning());
        static::assertNull($entity->getGeneratedAt());
    }

    private function ensureProductStream(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $exists = (bool) $connection->fetchOne('SELECT COUNT(*) FROM product_stream WHERE id = UNHEX(?)', ['137b079935714281ba80b40f83f8d7eb']);
        if ($exists) {
            return;
        }

        $connection->executeStatement('
            INSERT INTO `product_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX(\'137B079935714281BA80B40F83F8D7EB\'), \'[]\', 0, \'2019-08-16 08:43:57.488\', NULL);
        ');
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

        $domainEntity = $repository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($domainEntity);

        return $domainEntity;
    }

    private function createTestEntity(string $filename): string
    {
        $id = Uuid::randomHex();
        $this->productExportRepository->upsert([
            [
                'id' => $id,
                'fileName' => $filename,
                'accessKey' => Uuid::randomHex(),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'fileFormat' => ProductExportEntity::FILE_FORMAT_CSV,
                'interval' => 10,
                'headerTemplate' => 'name,url',
                'bodyTemplate' => '{{ product.name }}',
                'productStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'storefrontSalesChannelId' => $this->getSalesChannelDomain()->getSalesChannelId(),
                'salesChannelId' => $this->getSalesChannelId(),
                'salesChannelDomainId' => $this->getSalesChannelDomain()->getId(),
                'generatedAt' => null,
                'generateByCronjob' => true,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        return $id;
    }
}
