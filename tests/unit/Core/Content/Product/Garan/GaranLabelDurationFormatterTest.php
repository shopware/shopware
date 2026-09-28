<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelDurationFormatter::class)]
class GaranLabelDurationFormatterTest extends TestCase
{
    private GaranLabelDurationFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new GaranLabelDurationFormatter();
    }

    public function testNullMonthsReturnsNull(): void
    {
        static::assertNull($this->formatter->formatMonths(null));
    }

    #[TestWith([24])]
    #[TestWith([0])]
    #[TestWith([-6])]
    public function testMonthsNotAboveThresholdReturnNull(int $months): void
    {
        static::assertNull($this->formatter->formatMonths($months));
    }

    #[TestWith([25])]
    #[TestWith([31])]
    #[TestWith([37])]
    public function testMonthsNotDivisibleBySixReturnNull(int $months): void
    {
        static::assertNull($this->formatter->formatMonths($months));
    }

    #[TestWith([36, '3'])]
    #[TestWith([48, '4'])]
    #[TestWith([60, '5'])]
    public function testFullYearsAreFormattedWithoutDecimal(int $months, string $expected): void
    {
        static::assertSame($expected, $this->formatter->formatMonths($months));
    }

    #[TestWith([30, '2,5'])]
    #[TestWith([42, '3,5'])]
    #[TestWith([54, '4,5'])]
    #[TestWith([306, '25,5'])]
    public function testHalfYearsAreFormattedWithDecimal(int $months, string $expected): void
    {
        static::assertSame($expected, $this->formatter->formatMonths($months));
    }

    /**
     * The label is deliberately language neutral, which is why it carries a translation strip for
     * all 24 languages, and the decimal separator on it is a comma regardless of locale.
     */
    #[TestWith(['de-DE'])]
    #[TestWith(['en-US'])]
    #[TestWith(['fr-FR'])]
    public function testDecimalSeparatorIsACommaInEveryLocale(string $locale): void
    {
        $previous = setlocale(\LC_NUMERIC, '0');
        setlocale(\LC_NUMERIC, $locale);

        try {
            static::assertSame('2,5', $this->formatter->formatMonths(30));
        } finally {
            setlocale(\LC_NUMERIC, \is_string($previous) ? $previous : 'C');
        }
    }
}
