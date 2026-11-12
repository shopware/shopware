<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceBlobPostUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Assert\Serialization;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class CheapestPriceBlobPostUpdaterTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testRebuildsMissingAndDifferentBlobsWithoutRewritingMatchingProducts(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $updater = $this->getUpdater();
        $initialTotal = $updater->getTotal();
        $products = [];
        foreach (['missing', 'different', 'matching'] as $name) {
            $products[] = (new ProductBuilder($ids, $name))->price(100)->build();
        }
        static::getContainer()->get('product.repository')->create($products, $context);

        $matching = serialize(new CheapestPriceContainer([]));
        $connection->executeStatement(
            'UPDATE product SET cheapest_price = :price, cheapest_price_blob = :price WHERE id = :id AND version_id = :version',
            ['price' => $matching, 'id' => $ids->getBytes('matching'), 'version' => Uuid::fromHexToBytes($context->getVersionId())]
        );
        $connection->executeStatement('UPDATE product SET cheapest_price = cheapest_price_blob, cheapest_price_blob = NULL WHERE id = :id', ['id' => $ids->getBytes('missing')]);
        // Simulate a legacy writer changing only the text column after a blob was populated.
        $connection->executeStatement('UPDATE product SET cheapest_price = :price WHERE id = :id', ['price' => 'legacy-write', 'id' => $ids->getBytes('different')]);

        static::assertSame($initialTotal + 2, $updater->getTotal());
        $iteratedIds = [];
        $offset = null;
        while ($message = $updater->iterate($offset)) {
            $data = $message->getData();
            static::assertIsArray($data);
            array_push($iteratedIds, ...$data);
            $updater->handle($message);
            $offset = $message->getOffset();
        }
        static::assertContains($ids->get('missing'), $iteratedIds);
        static::assertContains($ids->get('different'), $iteratedIds);
        static::assertNotContains($ids->get('matching'), $iteratedIds);

        foreach (['missing', 'different'] as $name) {
            $blob = $connection->fetchOne('SELECT cheapest_price_blob FROM product WHERE id = :id', ['id' => $ids->getBytes($name)]);
            static::assertIsString($blob);
            $container = Serialization::assertUnserializedInstanceOf(CheapestPriceContainer::class, $blob);
            static::assertSame(100.0, $container->resolve($context)?->getPrice()->first()?->getGross());
        }
        static::assertSame($matching, $connection->fetchOne('SELECT cheapest_price_blob FROM product WHERE id = :id', ['id' => $ids->getBytes('matching')]));
    }

    public function testRepairRemainsScheduledUntilTheLastBatch(): void
    {
        $updater = $this->getUpdater();
        $queue = new IndexerQueuer(static::getContainer()->get(Connection::class));
        $offset = null;
        while ($message = $updater->iterate($offset)) {
            static::assertArrayHasKey(CheapestPriceBlobPostUpdater::NAME, $queue->getIndexers());
            $updater->handle($message);
            $offset = $message->getOffset();
        }

        static::assertArrayNotHasKey(CheapestPriceBlobPostUpdater::NAME, $queue->getIndexers());
    }

    public function testDoesNotIterateAlreadyReconciledProducts(): void
    {
        $ids = new IdsCollection();
        $product = (new ProductBuilder($ids, 'already-reconciled'))->price(100)->build();
        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());
        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE product SET cheapest_price = cheapest_price_blob WHERE id = :id',
            ['id' => $ids->getBytes('already-reconciled')]
        );

        $updater = $this->getUpdater();
        static::assertSame(0, $updater->getTotal());
        static::assertNull($updater->iterate(null));
    }

    public function testRebuildsDraftPricesInTheirOwnVersion(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $repository = static::getContainer()->get('product.repository');
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $product = (new ProductBuilder($ids, 'versioned'))->price(75)->build();
        $repository->create([$product], $context);
        $versionId = $repository->createVersion($product['id'], $context);
        $liveBlob = serialize(new CheapestPriceContainer([]));
        $connection->executeStatement(
            'UPDATE product SET cheapest_price = :blob, cheapest_price_blob = :blob WHERE id = :id AND version_id = :version',
            ['blob' => $liveBlob, 'id' => $ids->getBytes('versioned'), 'version' => Uuid::fromHexToBytes($context->getVersionId())]
        );
        $connection->executeStatement(
            'UPDATE product SET cheapest_price = :legacy, cheapest_price_blob = NULL WHERE id = :id AND version_id = :version',
            ['legacy' => 'legacy-write', 'id' => $ids->getBytes('versioned'), 'version' => Uuid::fromHexToBytes($versionId)]
        );

        $this->getUpdater()->handle(new EntityIndexingMessage([$product['id']], null, $context));

        $blob = $connection->fetchOne(
            'SELECT cheapest_price_blob FROM product WHERE id = :id AND version_id = :version',
            ['id' => $ids->getBytes('versioned'), 'version' => Uuid::fromHexToBytes($versionId)]
        );
        static::assertIsString($blob);
        $container = Serialization::assertUnserializedInstanceOf(CheapestPriceContainer::class, $blob);
        static::assertSame(75.0, $container->resolve($context)?->getPrice()->first()?->getGross());
        static::assertSame($liveBlob, $connection->fetchOne(
            'SELECT cheapest_price_blob FROM product WHERE id = :id AND version_id = :version',
            ['id' => $ids->getBytes('versioned'), 'version' => Uuid::fromHexToBytes($context->getVersionId())]
        ));
    }

    public function testFailedBatchKeepsTheRepairScheduled(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $product = (new ProductBuilder($ids, 'failed'))->price(100)->build();
        static::getContainer()->get('product.repository')->create([$product], $context);
        $connection->executeStatement('UPDATE product SET cheapest_price = cheapest_price_blob, cheapest_price_blob = NULL WHERE id = :id', ['id' => $ids->getBytes('failed')]);

        $failure = new \RuntimeException('Price indexing failed');
        $priceUpdater = $this->createMock(CheapestPriceUpdater::class);
        $priceUpdater->expects($this->once())->method('update')->willThrowException($failure);
        $updater = new CheapestPriceBlobPostUpdater(
            static::getContainer()->get(IteratorFactory::class),
            $connection,
            $priceUpdater,
        );
        static::assertNotNull($updater->iterate(null));

        $this->expectExceptionObject($failure);
        try {
            $updater->handle(new EntityIndexingMessage([$product['id']], null, $context));
        } finally {
            static::assertArrayHasKey(CheapestPriceBlobPostUpdater::NAME, (new IndexerQueuer($connection))->getIndexers());
        }
    }

    private function getUpdater(): CheapestPriceBlobPostUpdater
    {
        return new CheapestPriceBlobPostUpdater(
            static::getContainer()->get(IteratorFactory::class),
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(CheapestPriceUpdater::class),
        );
    }
}
