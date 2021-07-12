<?php
declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1622010069AddCartRules;
use Shopware\Core\Migration\V6_4\Migration1625816310AddDefaultToCartRuleIds;

class Migration1625816310AddDefaultToCartRuleIdsTest extends TestCase
{
    use KernelTestBehaviour;
    use BasicTestDataBehaviour;

    public function testCartRuleIdsGetSets(): void
    {
        $c = $this->getContainer()->get(Connection::class);
        $c->executeStatement('ALTER TABLE `cart` DROP `rule_ids`;');
        $c->executeStatement('TRUNCATE `cart`');

        $c->insert(
            'cart',
            [
                'token' => Uuid::randomHex(),
                'name' => Uuid::randomHex(),
                'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
                'shipping_method_id' => Uuid::fromHexToBytes($this->getValidShippingMethodId()),
                'payment_method_id' => Uuid::fromHexToBytes($this->getValidPaymentMethodId()),
                'country_id' => Uuid::fromHexToBytes($this->getValidCountryId()),
                'sales_channel_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL),
                'customer_id' => null,
                'price' => 10,
                'line_item_count' => 1,
                'cart' => '',
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
