<?php
declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Currency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(CurrencyFormatter::class)]
class CurrencyFormatterTest extends TestCase
{
    private MockObject&LanguageLocaleCodeProvider $localeProvider;

    private CurrencyFormatter $formatter;

    protected function setUp(): void
    {
        $this->localeProvider = static::createMock(LanguageLocaleCodeProvider::class);
        $this->formatter = new CurrencyFormatter($this->localeProvider);
    }

    #[DataProvider('formattingParameterProvider')]
    public function testFormatCurrencyByLanguageWillUseProvidedDecimalPlaces(float $price, int $decimalPlaces, string $localeCode, string $expectedSeparator, string $currencyISO, string $expectedCurrencySymbol): void
    {
        $this->localeProvider->expects($this->once())->method('getLocaleForLanguageId')->willReturn($localeCode);
        $pattern = \sprintf('/\%s\d{%s}/', $expectedSeparator, (string) $decimalPlaces);
        $formattedPrice = $this->formatter->formatCurrencyByLanguage(
            $price,
            $currencyISO,
            Uuid::randomHex(),
            $this->createContext($decimalPlaces),
            3
        );

        static::assertMatchesRegularExpression($pattern, $formattedPrice);
    }

    /**
     * @param non-empty-string $expectedCurrencySymbol
     */
    #[DataProvider('formattingParameterProvider')]
    public function testFormatCurrencyByLanguageWillWriteCorrectCurrencySymbol(float $price, int $decimalPlaces, string $localeCode, string $expectedSeparator, string $currencyISO, string $expectedCurrencySymbol): void
    {
        $this->localeProvider->expects($this->once())->method('getLocaleForLanguageId')->willReturn($localeCode);
        $formattedPrice = $this->formatter->formatCurrencyByLanguage(
            $price,
            $currencyISO,
            Uuid::randomHex(),
            $this->createContext($decimalPlaces)
        );

        static::assertThat(
            $formattedPrice,
            static::logicalOr(
                static::stringStartsWith($expectedCurrencySymbol),
                static::stringEndsWith($expectedCurrencySymbol)
            )
        );
    }

    public function testResetWillRemoveExistingFormatters(): void
    {
        $this->formatter->formatCurrencyByLanguage(19.9999, 'EUR', Uuid::randomHex(), $this->createContext(2));

        static::assertNotEmpty((new \ReflectionProperty(CurrencyFormatter::class, 'formatter'))->getValue($this->formatter));
        $this->formatter->reset();

        static::assertEmpty((new \ReflectionProperty(CurrencyFormatter::class, 'formatter'))->getValue($this->formatter));
    }

    /**
     * @return iterable<string, array{price: float, decimalPlaces: int, localeCode: non-empty-string, expectedSeparator: non-empty-string, currencyISO: non-empty-string, expectedCurrencySymbol: non-empty-string}>
     */
    public static function formattingParameterProvider(): iterable
    {
        yield 'Spanish euro formatting uses comma decimals and euro symbol' => [
            'price' => 71.01,
            'decimalPlaces' => 2,
            'localeCode' => 'es-ES',
            'expectedSeparator' => ',',
            'currencyISO' => 'EUR',
            'expectedCurrencySymbol' => '€',
        ];
        yield 'Czech koruna formatting uses comma decimals and koruna symbol' => [
            'price' => 7.10,
            'decimalPlaces' => 2,
            'localeCode' => 'cs-CZ',
            'expectedSeparator' => ',',
            'currencyISO' => 'CZK',
            'expectedCurrencySymbol' => 'Kč',
        ];
        yield 'British pound formatting uses dot decimals and pound symbol' => [
            'price' => 0.71,
            'decimalPlaces' => 3,
            'localeCode' => 'en-GB',
            'expectedSeparator' => '.',
            'currencyISO' => 'GBP',
            'expectedCurrencySymbol' => '£',
        ];
    }

    private function createContext(int $decimals): Context
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
            new CashRoundingConfig($decimals, 0.01, true)
        );
    }
}
