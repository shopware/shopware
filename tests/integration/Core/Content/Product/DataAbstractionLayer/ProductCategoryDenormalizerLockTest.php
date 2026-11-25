<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * Runs without the usual test transaction: another connection can only lock committed rows.
 *
 * @internal
 */
#[Package('inventory')]
class ProductCategoryDenormalizerLockTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    private string $parentId;

    private string $variantId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        static::assertSame(0, $this->connection->getTransactionNestingLevel());

        $this->ids = new IdsCollection();
        $parentNumber = Uuid::randomHex();
        $variantNumber = Uuid::randomHex();
        (new ProductBuilder($this->ids, $parentNumber))
            ->price(100)
            ->category('category')
            ->variant((new ProductBuilder($this->ids, $variantNumber))->price(100)->build())
            ->write(static::getContainer());

        $this->parentId = $this->ids->get($parentNumber);
        $this->variantId = $this->ids->get($variantNumber);
    }

    protected function tearDown(): void
    {
        $context = Context::createDefaultContext();
        static::getContainer()->get('product.repository')->delete([['id' => $this->parentId]], $context);
        static::getContainer()->get('category.repository')->delete([['id' => $this->ids->get('category')]], $context);
        static::getContainer()->get('tax.repository')->delete([['id' => $this->ids->get('t1')]], $context);
    }

    public function testRewriteWaitsForAVariantLockedByAnotherConnection(): void
    {
        $blocker = DriverManager::getConnection($this->connection->getParams());
        $originalTimeout = (int) $this->connection->fetchOne('SELECT @@SESSION.innodb_lock_wait_timeout');

        $blocker->beginTransaction();
        // a shared lock: only an exclusive lock of the rewrite has to wait for it
        $blocker->fetchOne('SELECT id FROM product WHERE id = :id LOCK IN SHARE MODE', ['id' => Uuid::fromHexToBytes($this->variantId)]);
        $this->connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
        // inside an outer transaction the rewrite rethrows the timeout instead of retrying it ten times
        $this->connection->beginTransaction();

        try {
            $this->expectException(LockWaitTimeoutException::class);

            static::getContainer()->get(ProductCategoryDenormalizer::class)->update([$this->parentId], Context::createDefaultContext());
        } finally {
            $this->connection->rollBack();
            $this->connection->executeStatement('SET SESSION innodb_lock_wait_timeout = ' . $originalTimeout);
            $blocker->rollBack();
            $blocker->close();
        }
    }
}
