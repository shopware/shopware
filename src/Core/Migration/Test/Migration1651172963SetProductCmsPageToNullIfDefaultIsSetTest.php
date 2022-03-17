<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1651172963SetProductCmsPageToNullIfDefaultIsSet;

/** @internal */
class Migration1651172963SetProductCmsPageToNullIfDefaultIsSetTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testItSetsProductCmsPageIdToNull(): void
    {
        $migration = new Migration1651172963SetProductCmsPageToNullIfDefaultIsSet();

        $product = [
            'id' => Uuid::randomBytes(),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'stock' => 10,
            'cms_page_id' => Uuid::fromHexToBytes(Defaults::CMS_PRODUCT_DETAIL_PAGE),
            'cms_page_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ];

        $countAffectedRows = $this->connection->insert('product', $product);

        // assert product was created with given cms page id
        static::assertEquals(1, $countAffectedRows);

        // should work as expected if executed multiple times
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        $result = $this->connection->fetchOne('
            SELECT id
            FROM  product
            WHERE cms_page_id = :cmsPageId
            ORDER BY created_at ASC;
       ', ['cmsPageId' => Uuid::fromHexToBytes(Defaults::CMS_PRODUCT_DETAIL_PAGE)]);

        // assert that no products with default cms page id are found
        static::assertFalse($result);
    }
}
