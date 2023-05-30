<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1618900427FixTotalRounding;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('core')]
class Migration1618900427FixTotalRoundingTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testUpdateOrder(): void
    {
        $ids = new IdsCollection();

        $order = [
            'id' => $ids->get('order'),
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'billingAddressId' => $ids->get('billing'),
            'currencyId' => Defaults::CURRENCY,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'stateId' => $this->getStateId(),
            'currencyFactor' => 1,
            'orderDateTime' => new \DateTime(),
            'price' => json_decode(json_encode(new CartPrice(1, 1, 1, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_FREE), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'shippingCosts' => json_decode(json_encode(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
        ];

        $this->getContainer()->get('order.repository')
            ->create([$order], Context::createDefaultContext());

        $this->getContainer()->get(Connection::class)
            ->executeStatement('UPDATE `order` SET total_rounding = NULL WHERE id = :id', ['id' => $ids->getBytes('order')]);

        $rounding = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT total_rounding FROM `order` WHERE id = :id', ['id' => $ids->getBytes('order')]);

        static::assertNull($rounding);

        $migration = new Migration1618900427FixTotalRounding();
        $migration->update($this->getContainer()->get(Connection::class));

        $rounding = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT total_rounding FROM `order` WHERE id = :id', ['id' => $ids->getBytes('order')]);

        static::assertNotNull($rounding);
    }

    private function getStateId(): string
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM state_machine_state LIMIT 1');
    }
}
