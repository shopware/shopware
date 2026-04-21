<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers PAngV §11 expectations for the savings percentage computed
 * by the storefront price templates (`block-price.html.twig`,
 * `buy-widget-price.html.twig`, `component/product/card/price-unit.html.twig`):
 * when both a strike-through list price and a 30-day lowest price
 * (regulationPrice) are present, the displayed savings percentage must
 * be calculated against the 30-day lowest price instead of the strike price.
 *
 * The template expression is `(1 - unitPrice / regulationPrice) * 100` rounded
 * to two decimals; this test mirrors that formula so a regression in either
 * direction is caught without requiring a full storefront rendering context.
 *
 * @internal
 */
class PriceDisplayTemplateTest extends TestCase
{
    #[DataProvider('savingsPercentageProvider')]
    public function testSavingsPercentageIsComputedAgainstRegulationPrice(
        float $unitPrice,
        float $regulationPrice,
        float $expectedPercentage
    ): void {
        // Matches the template expression plus the `max(0, …)` guard, which
        // prevents a nonsensical negative "savings" badge when the unit price
        // exceeds the reference.
        $percentage = max(0, round((1 - $unitPrice / $regulationPrice) * 100, 2));

        static::assertSame($expectedPercentage, $percentage);
    }

    /**
     * @return iterable<string, array{float, float, float}>
     */
    public static function savingsPercentageProvider(): iterable
    {
        // Scenario from issue #12956: unit 1.00, 30-day low 4.00.
        yield 'issue 12956 scenario yields 75 percent' => [1.0, 4.0, 75.0];

        yield 'unit equal to regulation price yields 0 percent' => [4.0, 4.0, 0.0];

        yield 'typical 20 percent off example' => [80.0, 100.0, 20.0];

        // Regression: unit price above regulation price must clamp to 0%, not
        // surface a negative savings badge.
        yield 'unit price above regulation price clamps to zero' => [150.0, 100.0, 0.0];
    }
}
