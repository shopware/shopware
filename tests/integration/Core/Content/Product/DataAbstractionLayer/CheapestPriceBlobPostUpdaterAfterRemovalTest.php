<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceBlobPostUpdater;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1794234846RemoveLegacyCheapestPrice;
use Shopware\Core\Test\Assert\Serialization;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class CheapestPriceBlobPostUpdaterAfterRemovalTest extends TestCase
{
    use KernelTestBehaviour;

    public function testRebuildsOnlyNullBlobsAfterDestructiveMigration(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $connection = static::getContainer()->get(Connection::class);
        $repository = static::getContainer()->get('product.repository');
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $offset = ['offset' => (int) $connection->fetchOne('SELECT COALESCE(MAX(auto_increment), 0) FROM product')];
        $products = [];
        for ($i = 0; $i < 50; ++$i) {
            $products[] = (new ProductBuilder($ids, 'populated-after-removal-' . $i))->price(100)->build();
        }
        $repository->create($products, $context);
        $matchingEnd = (int) $connection->fetchOne('SELECT MAX(auto_increment) FROM product');
        $missing = (new ProductBuilder($ids, 'missing-after-removal'))->price(100)->build();
        $repository->create([$missing], $context);
        $products[] = $missing;

        $storedPrices = $connection->fetchAllAssociative('SELECT id, version_id, cheapest_price FROM product WHERE cheapest_price IS NOT NULL');
        $existingBlob = serialize(new CheapestPriceContainer([]));
        $connection->executeStatement('UPDATE product SET cheapest_price_blob = NULL WHERE id = :id', ['id' => $ids->getBytes('missing-after-removal')]);
        $connection->executeStatement(
            'UPDATE product SET cheapest_price_blob = :blob WHERE auto_increment > :offset AND auto_increment <= :end',
            ['blob' => $existingBlob, 'offset' => $offset['offset'], 'end' => $matchingEnd]
        );

        try {
            (new Migration1794234846RemoveLegacyCheapestPrice())->updateDestructive($connection);
            static::assertFalse(TableHelper::columnExists($connection, 'product', 'cheapest_price'));

            $updater = static::getContainer()->get(CheapestPriceBlobPostUpdater::class);
            $matchingBatch = $updater->iterate($offset);
            static::assertNotNull($matchingBatch);
            static::assertSame([], $matchingBatch->getData());
            static::assertSame(['offset' => $matchingEnd], $matchingBatch->getOffset());
            $updater->handle($matchingBatch);
            $offset = $matchingBatch->getOffset();
            while ($message = $updater->iterate($offset)) {
                $data = $message->getData();
                static::assertIsArray($data);
                static::assertNotContains($ids->get('populated-after-removal-0'), $data);
                $updater->handle($message);
                $offset = $message->getOffset();
            }

            $blob = $connection->fetchOne('SELECT cheapest_price_blob FROM product WHERE id = :id AND version_id = :version', [
                'id' => $ids->getBytes('missing-after-removal'),
                'version' => Uuid::fromHexToBytes($context->getVersionId()),
            ]);
            static::assertIsString($blob);
            $container = Serialization::assertUnserializedInstanceOf(CheapestPriceContainer::class, $blob);
            static::assertSame(100.0, $container->resolve($context)?->getPrice()->first()?->getGross());
            static::assertSame($existingBlob, $connection->fetchOne('SELECT cheapest_price_blob FROM product WHERE id = :id', ['id' => $ids->getBytes('populated-after-removal-0')]));
        } finally {
            // DDL commits implicitly; restore the shared test schema and data without a transaction.
            if (!TableHelper::columnExists($connection, 'product', 'cheapest_price')) {
                $connection->executeStatement('ALTER TABLE product ADD COLUMN cheapest_price LONGTEXT NULL');
            }
            foreach ($storedPrices as $row) {
                $connection->update('product', ['cheapest_price' => $row['cheapest_price']], ['id' => $row['id'], 'version_id' => $row['version_id']]);
            }
            $repository->delete(array_map(static fn (array $product): array => ['id' => $product['id']], $products), $context);
        }
    }
}
