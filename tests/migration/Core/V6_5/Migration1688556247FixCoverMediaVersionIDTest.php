<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1688556247FixCoverMediaVersionID;

/**
 * @internal
 */
#[CoversClass(Migration1688556247FixCoverMediaVersionID::class)]
class Migration1688556247FixCoverMediaVersionIDTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $product = (new ProductBuilder(new IdsCollection(), 'test'))
            ->cover('cover')
            ->price(100)
            ->build();

        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM product');

        $this->getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        $connection->executeStatement('UPDATE product SET product_media_version_id = NULL');

        $migration = new Migration1688556247FixCoverMediaVersionID();
        $migration->update($connection);

        $result = $connection->fetchOne('SELECT COUNT(1) FROM product WHERE product_media_version_id IS NULL');
        static::assertSame('0', $result);

        $data = $connection->fetchAssociative('SELECT * FROM product');

        $migration->update($connection);

        $newData = $connection->fetchAssociative('SELECT * FROM product');
        static::assertEquals($data, $newData, 'Data should not change after second migration run');
    }
}
