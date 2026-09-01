<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Stock;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
class StockStorageConcurrencyTest extends TestCase
{
    use BasicTestDataBehaviour;
    use KernelTestBehaviour;

    public function testSiblingVariantAvailabilityDoesNotWaitForLockedParent(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');

        $taxId = $this->getValidTaxId();
        $price = [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.4, 'linked' => false]];
        $parent = [
            'id' => $ids->get('stock-lock-parent'),
            'productNumber' => $ids->get('stock-lock-parent-number'),
            'name' => 'Stock lock parent',
            'type' => ProductDefinition::TYPE_PHYSICAL,
            'taxId' => $taxId,
            'price' => $price,
            'stock' => 10,
            'isCloseout' => true,
        ];
        $firstVariant = [
            'id' => $ids->get('stock-lock-first-variant'),
            'parentId' => $ids->get('stock-lock-parent'),
            'productNumber' => $ids->get('stock-lock-first-variant-number'),
            'name' => 'Stock lock first variant',
            'type' => ProductDefinition::TYPE_PHYSICAL,
            'taxId' => $taxId,
            'price' => $price,
            'stock' => 5,
            'isCloseout' => null,
        ];
        $secondVariant = [
            'id' => $ids->get('stock-lock-second-variant'),
            'parentId' => $ids->get('stock-lock-parent'),
            'productNumber' => $ids->get('stock-lock-second-variant-number'),
            'name' => 'Stock lock second variant',
            'type' => ProductDefinition::TYPE_PHYSICAL,
            'taxId' => $taxId,
            'price' => $price,
            'stock' => 5,
            'isCloseout' => null,
        ];

        $productRepository->create([$parent], $context);
        $productRepository->create([$firstVariant, $secondVariant], $context);

        $firstConnection = $this->createConnection();
        $secondConnection = $this->createConnection();
        $dispatcher = static::createStub(EventDispatcherInterface::class);

        try {
            $firstConnection->beginTransaction();
            (new StockStorage($firstConnection, $dispatcher))->index([$ids->get('stock-lock-first-variant')], $context);
            $firstConnection->executeStatement(
                'SELECT id FROM product WHERE id = :id AND version_id = :version FOR UPDATE',
                [
                    'id' => $ids->getBytes('stock-lock-parent'),
                    'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                ]
            );

            $secondConnection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
            $secondConnection->beginTransaction();
            (new StockStorage($secondConnection, $dispatcher))->index([$ids->get('stock-lock-second-variant')], $context);

            static::assertTrue($secondConnection->isTransactionActive());
        } finally {
            $secondConnection->close();
            $firstConnection->close();

            $productRepository->delete([
                ['id' => $ids->get('stock-lock-first-variant')],
                ['id' => $ids->get('stock-lock-second-variant')],
            ], $context);
            $productRepository->delete([['id' => $ids->get('stock-lock-parent')]], $context);
        }
    }

    private function createConnection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);

        return new Connection(
            array_merge($connection->getParams(), ['dbname' => $connection->getDatabase() ?? '']),
            $connection->getDriver(),
            $connection->getConfiguration(),
        );
    }
}
