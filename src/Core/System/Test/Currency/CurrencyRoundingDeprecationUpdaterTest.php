<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Currency;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use function Flag\skipTestNext6059;

class CurrencyRoundingDeprecationUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function tearDown(): void
    {
        $this->setBlueGreen(true);
    }

    public function createProvider()
    {
        return [
            'Write old value' => [
                2,
                null,
                ['decimals' => 2, 'roundForNet' => true, 'interval' => 0.01],
            ],
            'Write new value' => [
                null,
                ['decimals' => 2, 'roundForNet' => true, 'interval' => 0.01],
                ['decimals' => 2, 'roundForNet' => true, 'interval' => 0.01],
            ],
            'Write other new value' => [
                null,
                ['decimals' => 4, 'roundForNet' => false, 'interval' => 0.01],
                ['decimals' => 4, 'roundForNet' => false, 'interval' => 0.01],
            ],
        ];
    }

    /**
     * @dataProvider createProvider
     */
    public function testCreate(?int $decimals, ?array $rounding, array $expected): void
    {
        skipTestNext6059($this);

        $this->setBlueGreen(false);

        $id = Uuid::randomHex();
        $payload = [
            'id' => $id,
            'factor' => 1,
            'symbol' => 'A',
            'isoCode' => 'de',
            'shortName' => 'test',
            'name' => 'test',
        ];

        if ($decimals) {
            $payload['decimalPrecision'] = $decimals;
        } else {
            $payload['itemRounding'] = $rounding;
            $payload['totalRounding'] = $rounding;
        }

        $this->getContainer()
            ->get('currency.repository')
            ->create([$payload], Context::createDefaultContext());

        $currency = $this->getContainer()
            ->get('currency.repository')
            ->search(new Criteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(CurrencyEntity::class, $currency);

        /** @var CurrencyEntity $currency */
        static::assertEquals($expected['decimals'], $currency->getDecimalPrecision());
        static::assertEquals($expected['decimals'], $currency->getItemRounding()->getDecimals());
        static::assertEquals($expected['roundForNet'], $currency->getItemRounding()->roundForNet());
        static::assertEquals($expected['interval'], $currency->getItemRounding()->getInterval());
    }

    public function updateProvider()
    {
        return [
            'Write old value' => [
                2,
                null,
                ['decimals' => 2, 'roundForNet' => false, 'interval' => 1.00],
            ],
            'Write new value' => [
                null,
                ['decimals' => 2, 'roundForNet' => true, 'interval' => 0.01],
                ['decimals' => 2, 'roundForNet' => true, 'interval' => 0.01],
            ],
            'Write other new value' => [
                null,
                ['decimals' => 4, 'roundForNet' => false, 'interval' => 0.01],
                ['decimals' => 4, 'roundForNet' => false, 'interval' => 0.01],
            ],
        ];
    }

    /**
     * @dataProvider updateProvider
     */
    public function testUpdate(?int $decimals, ?array $rounding, array $expected): void
    {
        skipTestNext6059($this);

        $this->setBlueGreen(false);

        $id = Uuid::randomHex();
        $payload = [
            'id' => $id,
            'factor' => 1,
            'symbol' => 'A',
            'isoCode' => 'de',
            'shortName' => 'test',
            'name' => 'test',
            'decimalPrecision' => 2,
            'itemRounding' => ['decimals' => 2, 'interval' => 1.00, 'roundForNet' => false],
            'totalRounding' => ['decimals' => 2, 'interval' => 1.00, 'roundForNet' => false],
        ];

        $this->getContainer()
            ->get('currency.repository')
            ->create([$payload], Context::createDefaultContext());

        if ($decimals) {
            $update = ['id' => $id, 'decimalPrecision' => $decimals];

            $this->getContainer()
                ->get('currency.repository')
                ->update([$update], Context::createDefaultContext());
        } else {
            $update = ['id' => $id, 'itemRounding' => $rounding];

            $this->getContainer()
                ->get('currency.repository')
                ->update([$update], Context::createDefaultContext());
        }

        $currency = $this->getContainer()
            ->get('currency.repository')
            ->search(new Criteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(CurrencyEntity::class, $currency);

        /** @var CurrencyEntity $currency */
        static::assertEquals($expected['decimals'], $currency->getDecimalPrecision());
        static::assertEquals($expected['decimals'], $currency->getItemRounding()->getDecimals());
        static::assertEquals($expected['roundForNet'], $currency->getItemRounding()->roundForNet());
        static::assertEquals($expected['interval'], $currency->getItemRounding()->getInterval());
    }

    private function setBlueGreen(?bool $enabled): void
    {
        $this->getContainer()->get(Connection::class)->rollBack();

        if ($enabled === null) {
            unset($_ENV['BLUE_GREEN_DEPLOYMENT']);
        } else {
            $_ENV['BLUE_GREEN_DEPLOYMENT'] = $enabled ? '1' : '0';
        }

        // reload env
        KernelLifecycleManager::bootKernel();

        $this->getContainer()->get(Connection::class)->beginTransaction();
        if ($enabled !== null) {
            $this->getContainer()->get(Connection::class)->executeUpdate('SET @TRIGGER_DISABLED = ' . ($enabled ? '0' : '1'));
        }
    }
}
