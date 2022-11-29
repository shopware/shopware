<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Facade;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @package checkout
 *
 * @internal
 */
class CartFacadeHelperTest extends TestCase
{
    use IntegrationTestBehaviour;

    private CartFacadeHelper $cartFacadeHelper;

    public function setUp(): void
    {
        $this->cartFacadeHelper = $this->getContainer()->get(CartFacadeHelper::class);
    }

    /**
     * @dataProvider priceCases
     */
    public function testPriceFactory(array $prices, PriceCollection $expected): void
    {
        $actual = $this->cartFacadeHelper->price($prices);

        foreach ($expected as $expectedPrice) {
            $actualPrice = $actual->getCurrencyPrice($expectedPrice->getCurrencyId());

            static::assertInstanceOf(Price::class, $actualPrice);
            static::assertEquals($expectedPrice->getNet(), $actualPrice->getNet());
            static::assertEquals($expectedPrice->getGross(), $actualPrice->getGross());
            static::assertEquals($expectedPrice->getLinked(), $actualPrice->getLinked());
        }
    }

    public function priceCases(): \Generator
    {
        yield 'manual price definition' => [
            [
                'default' => ['gross' => 100, 'net' => 90],
                'USD' => ['gross' => 90, 'net' => 80],
            ],
            new PriceCollection([
                new Price(Defaults::CURRENCY, 90, 100, false),
                new Price($this->getCurrencyIdByIso('USD'), 80, 90, false),
            ]),
        ];

        yield 'storage price definition' => [
            [
                ['gross' => 100, 'net' => 90, 'linked' => true, 'currencyId' => Defaults::CURRENCY],
                ['gross' => 90, 'net' => 80, 'linked' => false, 'currencyId' => $this->getCurrencyIdByIso('USD')],
            ],
            new PriceCollection([
                new Price(Defaults::CURRENCY, 90, 100, true),
                new Price($this->getCurrencyIdByIso('USD'), 80, 90, false),
            ]),
        ];
    }
}
