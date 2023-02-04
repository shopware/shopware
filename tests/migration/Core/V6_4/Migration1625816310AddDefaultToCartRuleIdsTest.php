<?php
declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1622010069AddCartRules;
use Shopware\Core\Migration\V6_4\Migration1625816310AddDefaultToCartRuleIds;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1625816310AddDefaultToCartRuleIds
 */
class Migration1625816310AddDefaultToCartRuleIdsTest extends TestCase
{
    public function testCartRuleIdsGetSets(): void
    {
        $c = KernelLifecycleManager::getConnection();

        if (EntityDefinitionQueryHelper::columnExists($c, 'cart', 'rule_ids')) {
            $c->executeStatement('ALTER TABLE `cart` DROP `rule_ids`;');
        }

        $c->executeStatement('TRUNCATE `cart`');

        // @deprecated tag:v6.6.0 - keep `$cartColumn = 'payload';`
        $cartColumn = 'cart';
        if (EntityDefinitionQueryHelper::columnExists($c, 'cart', 'payload')) {
            $cartColumn = 'payload';
        }

        $c->insert(
            'cart',
            [
                'token' => Uuid::randomHex(),
                'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
                'shipping_method_id' => $c->fetchOne('SELECT id FROM `shipping_method` WHERE `active` = 1'),
                'payment_method_id' => $c->fetchOne('SELECT id FROM `payment_method` WHERE `active` = 1'),
                'country_id' => $c->fetchOne('SELECT id FROM `country` WHERE `active` = 1'),
                'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                'customer_id' => null,
                'price' => 10,
                'line_item_count' => 1,
                $cartColumn => '',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $columnAddMigration = new Migration1622010069AddCartRules();
        $columnAddMigration->update($c);

        $value = $c->fetchFirstColumn('SELECT rule_ids FROM cart')[0];
        if ($value === 'null' || $value === null) {
            $value = '';
        }

        // MariaDB returns an empty string, MySQL 5.7 returns null
        static::assertEquals('', $value);

        $setDefaultMigration = new Migration1625816310AddDefaultToCartRuleIds();
        $setDefaultMigration->update($c);

        $value = $c->fetchFirstColumn('SELECT rule_ids FROM cart')[0];
        static::assertTrue($value === '[]' || $value === 'null', 'The rules_id must be empty array or null');
    }
}
