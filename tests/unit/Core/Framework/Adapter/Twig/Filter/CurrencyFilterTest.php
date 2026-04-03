<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\Filter\CurrencyFilter;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CurrencyFilter::class)]
class CurrencyFilterTest extends TestCase
{
    #[DataProvider('symbolReplacementProvider')]
    public function testCurrencyFilterReplacesIsoCodeWithSymbol(string $formatterResult, string $isoCode, string $symbol, string $expected): void
    {
        $context = $this->createContext();

        $currencyEntity = new CurrencyEntity();
        $currencyEntity->setId(Uuid::randomHex());
        $currencyEntity->setIsoCode($isoCode);
        $currencyEntity->setSymbol($symbol);
        $currencyEntity->setFactor(1.0);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCurrency')->willReturn($currencyEntity);
        $salesChannelContext->method('getContext')->willReturn($context);

        $currencyFormatter = $this->createMock(CurrencyFormatter::class);
        $currencyFormatter->method('formatCurrencyByLanguage')->willReturn($formatterResult);

        $filter = new CurrencyFilter($currencyFormatter);

        $result = $filter->formatCurrency(
            ['context' => $salesChannelContext],
            10.00,
        );

        static::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function symbolReplacementProvider(): array
    {
        return [
            'PLN ISO code replaced with symbol' => ['10,00 PLN', 'PLN', 'zł', '10,00 zł'],
            'SEK ISO code replaced with symbol' => ['10,00 SEK', 'SEK', 'kr', '10,00 kr'],
            'EUR symbol stays unchanged' => ['10,00 €', 'EUR', '€', '10,00 €'],
            'GBP symbol stays unchanged' => ['£10.00', 'GBP', '£', '£10.00'],
        ];
    }

    private function createContext(): Context
    {
        return new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM],
            Defaults::LIVE_VERSION,
            1,
            true,
            CartPrice::TAX_STATE_GROSS,
            new CashRoundingConfig(2, 0.01, true)
        );
    }
}
