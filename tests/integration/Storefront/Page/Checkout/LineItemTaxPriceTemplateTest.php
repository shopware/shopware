<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Page\Checkout;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\Test\Generator;
use Twig\Environment;

/**
 * @internal
 */
#[Package('checkout')]
class LineItemTaxPriceTemplateTest extends TestCase
{
    use KernelTestBehaviour;

    #[DataProvider('lineItemTaxes')]
    public function testTheTaxColumnShowsTheTaxAmountOfTheLineItem(CalculatedTaxCollection $calculatedTaxes, string $expectedTaxAmount): void
    {
        $lineItem = new LineItem('line-item', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setPrice(new CalculatedPrice(10.0, 10.0, $calculatedTaxes, new TaxRuleCollection()));

        $currency = new CurrencyEntity();
        $currency->setId(Defaults::CURRENCY);
        $currency->setIsoCode('EUR');
        $currency->setFactor(1.0);

        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $html = $twig->render('@Storefront/storefront/component/line-item/element/tax-price.html.twig', [
            'context' => Generator::generateSalesChannelContext(currency: $currency),
            'lineItem' => $lineItem,
        ]);

        static::assertStringContainsString($expectedTaxAmount, $html);
    }

    /**
     * @return \Generator<string, array{CalculatedTaxCollection, string}>
     */
    public static function lineItemTaxes(): \Generator
    {
        yield 'a tax-free line item shows a zero amount instead of an empty cell' => [
            new CalculatedTaxCollection(),
            '€0.00',
        ];

        yield 'a taxed line item shows its tax amount' => [
            new CalculatedTaxCollection([new CalculatedTax(1.6, 19.0, 10.0)]),
            '€1.60',
        ];
    }
}
