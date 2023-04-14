<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Facade;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
class CartFacadeHelperTest extends TestCase
{
    use IntegrationTestBehaviour;

    private CartFacadeHelper $cartFacadeHelper;

    protected function setUp(): void
    {
        $this->cartFacadeHelper = $this->getContainer()->get(CartFacadeHelper::class);
    }

    /**
     * @dataProvider priceCases
     */
    public function testPriceFactory(array $prices, PriceCollection $expected): void
    {
        foreach ($prices as &$price) {
            if (isset($price['currencyId']) && $price['currencyId'] === 'USD') {
                $price['currencyId'] = $this->getCurrencyIdByIso('USD');
            }
        }

        $actual = $this->cartFacadeHelper->price($prices);

        foreach ($expected as $expectedPrice) {
            $currencyId = $expectedPrice->getCurrencyId();

            if ($currencyId === 'USD') {
                $currencyId = $this->getCurrencyIdByIso('USD');
            }

            $actualPrice = $actual->getCurrencyPrice($currencyId);

            static::assertInstanceOf(Price::class, $actualPrice);
            static::assertEquals($expectedPrice->getNet(), $actualPrice->getNet());
            static::assertEquals($expectedPrice->getGross(), $actualPrice->getGross());
            static::assertEquals($expectedPrice->getLinked(), $actualPrice->getLinked());
        }
    }

    public static function priceCases(): \Generator
    {
        yield 'manual price definition' => [
            [
                'default' => ['gross' => 100, 'net' => 90],
                'USD' => ['gross' => 90, 'net' => 80],
            ],
            new PriceCollection([
                new Price(Defaults::CURRENCY, 90, 100, false),
                new Price('USD', 80, 90, false),
            ]),
        ];

        yield 'storage price definition' => [
            [
                ['gross' => 100, 'net' => 90, 'linked' => true, 'currencyId' => Defaults::CURRENCY],
                ['gross' => 90, 'net' => 80, 'linked' => false, 'currencyId' => 'USD'],
            ],
            new PriceCollection([
                new Price(Defaults::CURRENCY, 90, 100, true),
                new Price('USD', 80, 90, false),
            ]),
        ];
    }
}
