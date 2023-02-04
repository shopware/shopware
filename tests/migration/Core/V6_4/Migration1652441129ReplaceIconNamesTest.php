<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1652441129ReplaceIconNames;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1652441129ReplaceIconNames
 */
class Migration1652441129ReplaceIconNamesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->replaceSalesChannelTypeIconName('regular-storefront', 'default-building-shop', $this->connection);
        $this->replaceSalesChannelTypeIconName('regular-shopping-basket', 'default-shopping-basket', $this->connection);
        $this->replaceSalesChannelTypeIconName('regular-rocket', 'default-object-rocket', $this->connection);
    }

    public function testRun(): void
    {
        $m = new Migration1652441129ReplaceIconNames();
        $m->update($this->connection);

        $icons = KernelLifecycleManager::getConnection()->fetchAllAssociative(
            'SELECT sctt.name as name, sct.icon_name as icon_name FROM sales_channel_type AS sct LEFT JOIN sales_channel_type_translation AS sctt
            ON sct.id = sctt.sales_channel_type_id ORDER BY name'
        );

        static::assertEquals($this->getAfterMigrationExpectation(), $icons);
    }

    public function testMultipleRuns(): void
    {
        $m = new Migration1652441129ReplaceIconNames();
        $m->update($this->connection);
        $m->update($this->connection);

        $icons = KernelLifecycleManager::getConnection()->fetchAllAssociative(
            'SELECT sctt.name as name, sct.icon_name as icon_name FROM sales_channel_type AS sct LEFT JOIN sales_channel_type_translation AS sctt
            ON sct.id = sctt.sales_channel_type_id ORDER BY name'
        );

        static::assertEquals($this->getAfterMigrationExpectation(), $icons);
    }

    private function replaceSalesChannelTypeIconName(string $oldIconName, string $newIconName, Connection $connection): void
    {
        $queryBuilder = $connection->createQueryBuilder();

        $oldIconSalesChannelTypes = $queryBuilder->select('id')
            ->from('sales_channel_type')
            ->where('icon_name = :iconName')
            ->setParameter('iconName', $oldIconName)
            ->execute()
            ->fetchFirstColumn();

        foreach ($oldIconSalesChannelTypes as $id) {
            $connection->executeStatement(
                'UPDATE `sales_channel_type` SET `icon_name` = :newIconName WHERE `id`= :id',
                [
                    'id' => $id,
                    'newIconName' => $newIconName,
                ]
            );
        }
    }

    /**
     * @return string[][]
     */
    private function getAfterMigrationExpectation(): array
    {
        return [
            [
                'name' => 'Headless',
                'icon_name' => 'regular-shopping-basket',
            ],
            [
                'name' => 'Headless',
                'icon_name' => 'regular-shopping-basket',
            ],
            [
                'name' => 'Product comparison',
                'icon_name' => 'default-object-rocket',
            ],
            [
                'name' => 'Produktvergleich',
                'icon_name' => 'default-object-rocket',
            ],
            [
                'name' => 'Storefront',
                'icon_name' => 'regular-storefront',
            ],
            [
                'name' => 'Storefront',
                'icon_name' => 'regular-storefront',
            ],
        ];
    }
}
