<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ProductStream;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductExportRepositoryTest extends TestCase
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

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product_export.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testCreateEntity(): void
    {
        $id = Uuid::randomHex();
        $this->repository->upsert([
            [
                'id' => $id,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'fileFormat' => ProductExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'bodyTemplate' => 'test',
                'productStreamId' => $this->getProductStreamId(),
                'salesChannelId' => $this->getSalesChannelId(),
                'salesChannelDomainId' => $this->getSalesChannelDomainId(),
                'generateByCronjob' => false,
            ],
        ], $this->context);

        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertSame('Testexport', $entity->getFileName());
        static::assertSame($id, $entity->getId());
    }

    public function testUpdateEntity(): void
    {
        $id = Uuid::randomHex();
        $this->repository->upsert([
            [
                'id' => $id,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'fileFormat' => ProductExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'bodyTemplate' => 'test',
                'productStreamId' => $this->getProductStreamId(),
                'salesChannelId' => $this->getSalesChannelId(),
                'salesChannelDomainId' => $this->getSalesChannelDomainId(),
                'generateByCronjob' => false,
            ],
        ], $this->context);
        $this->repository->upsert([
            [
                'id' => $id,
                'fileName' => 'Newexport',
            ],
        ], $this->context);

        $entity = $this->repository->search(new Criteria([$id]), $this->context)->get($id);
        static::assertSame('Newexport', $entity->getFileName());
        static::assertSame($id, $entity->getId());
    }

    public function testFetchProductStream(): void
    {
        $id = Uuid::randomHex();
        $this->repository->upsert([
            [
                'id' => $id,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'fileFormat' => ProductExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'bodyTemplate' => 'test',
                'productStreamId' => $this->getProductStreamId(),
                'salesChannelId' => $this->getSalesChannelId(),
                'salesChannelDomainId' => $this->getSalesChannelDomainId(),
                'generateByCronjob' => false,
            ],
        ], $this->context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('productStream');
        $entity = $this->repository->search($criteria, $this->context)->get($id);

        static::assertNotNull($entity->getProductStream());
    }

    public function testFetchSalesChannel(): void
    {
        $id = Uuid::randomHex();
        $this->repository->upsert([
            [
                'id' => $id,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'fileFormat' => ProductExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'bodyTemplate' => 'test',
                'productStreamId' => $this->getProductStreamId(),
                'salesChannelId' => $this->getSalesChannelId(),
                'salesChannelDomainId' => $this->getSalesChannelDomainId(),
                'generateByCronjob' => false,
            ],
        ], $this->context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('salesChannel');
        $entity = $this->repository->search($criteria, $this->context)->get($id);

        static::assertNotNull($entity->getSalesChannel());
    }

    public function testFetchSalesChannelDomain(): void
    {
        $id = Uuid::randomHex();
        $this->repository->upsert([
            [
                'id' => $id,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'fileFormat' => ProductExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'bodyTemplate' => 'test',
                'productStreamId' => $this->getProductStreamId(),
                'salesChannelId' => $this->getSalesChannelId(),
                'salesChannelDomainId' => $this->getSalesChannelDomainId(),
                'generateByCronjob' => false,
            ],
        ], $this->context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('salesChannelDomain');
        $entity = $this->repository->search($criteria, $this->context)->get($id);

        static::assertNotNull($entity->getSalesChannelDomain());
    }

    protected function getProductStreamId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('product_stream.repository');

        return $repository->search(new Criteria(), $this->context)->first()->getId();
    }

    protected function getSalesChannelId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('sales_channel.repository');

        return $repository->search(new Criteria(), $this->context)->first()->getId();
    }

    protected function getSalesChannelDomainId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('sales_channel_domain.repository');

        return $repository->search(new Criteria(), $this->context)->first()->getId();
    }
}
