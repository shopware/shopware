<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1626241110PromotionPreventCombination;

class Migration1626241110PromotionPreventCombinationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->rollBack();
        $this->connection->executeStatement('ALTER TABLE `promotion` DROP COLUMN `prevent_combination`;');
    }

    public function testUpdate(): void
    {
        $migration = new Migration1626241110PromotionPreventCombination();

        $this->connection->insert(
            'promotion',
            [
                'id' => Uuid::randomBytes(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'code' => 'phpUnit',
            ]
        );

        $promotionCount = (int) $this->connection->fetchOne('SELECT COUNT(`id`) FROM `promotion` WHERE `code` = \'phpUnit\';');
        static::assertSame(1, $promotionCount);

        $migration->update($this->connection);

        $promotion = $this->connection->fetchAssociative('SELECT * FROM `promotion` WHERE `code` = \'phpUnit\';');
        static::assertArrayHasKey('prevent_combination', $promotion);
        static::assertFalse((bool) $promotion['prevent_combination']);

        $this->connection->delete(
            'promotion',
            [
                'code' => 'phpUnit',
            ]
        );

        $this->connection->beginTransaction();
    }
}
