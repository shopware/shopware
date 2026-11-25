<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Util\FormulaEscaper;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(FormulaEscaper::class)]
class FormulaEscaperTest extends TestCase
{
    #[DataProvider('valueProvider')]
    public function testOnlyValuesStartingWithAFormulaTriggerArePrefixedWithAQuote(mixed $value, mixed $expected): void
    {
        static::assertSame($expected, FormulaEscaper::escape($value));
    }

    #[DataProvider('valueProvider')]
    public function testUnescapeGivesBackTheValueBeforeEscaping(mixed $value, mixed $escaped): void
    {
        static::assertSame($value, FormulaEscaper::unescape($escaped));
    }

    #[DataProvider('importedValueProvider')]
    public function testUnescapeRemovesOnlyAQuoteThatEscapingAdds(mixed $value, mixed $expected): void
    {
        static::assertSame($expected, FormulaEscaper::unescape($value));
    }

    /**
     * @return \Generator<string, array{mixed, mixed}>
     */
    public static function valueProvider(): \Generator
    {
        yield 'equals sign is escaped' => ['=1+1', '\'=1+1'];
        yield 'plus sign is escaped' => ['+1+1', '\'+1+1'];
        yield 'minus sign is escaped' => ['-1+1', '\'-1+1'];
        yield 'at sign is escaped' => ['@SUM(1)', '\'@SUM(1)'];
        yield 'phone number with spaces is escaped' => ['+49 30 123456', '\'+49 30 123456'];
        yield 'leading tab is escaped' => ["\t=1+1", "'\t=1+1"];
        yield 'leading carriage return is escaped' => ["\r=1+1", "'\r=1+1"];
        yield 'leading line feed is escaped' => ["\n=1+1", "'\n=1+1"];
        yield 'number after a leading tab is escaped' => ["\t1", "'\t1"];
        yield 'number after a leading line feed is escaped' => ["\n5", "'\n5"];
        yield 'full-width equals sign is escaped' => ['＝1+1', '\'＝1+1'];
        yield 'full-width plus sign is escaped' => ['＋1', '\'＋1'];
        yield 'full-width minus sign is escaped' => ['－1', '\'－1'];
        yield 'full-width at sign is escaped' => ['＠SUM(1)', '\'＠SUM(1)'];
        yield 'negative number stays' => ['-2', '-2'];
        yield 'negative decimal number stays' => ['-1.5', '-1.5'];
        yield 'number with a plus sign stays' => ['+49123', '+49123'];
        yield 'number with an exponent stays' => ['-1e5', '-1e5'];
        yield 'quoted formula gets another quote' => ['\'=1+1', '\'\'=1+1'];
        yield 'quoted number stays' => ['\'-2', '\'-2'];
        yield 'quoted text stays' => ['\'Main Street 1', '\'Main Street 1'];
        yield 'trigger after the first character stays' => ['a=b', 'a=b'];
        yield 'plain text stays' => ['Main Street 1', 'Main Street 1'];
        yield 'empty string stays' => ['', ''];
        yield 'null stays' => [null, null];
        yield 'value that is not a string stays' => [-1, -1];
    }

    /**
     * @return \Generator<string, array{mixed, mixed}>
     */
    public static function importedValueProvider(): \Generator
    {
        yield 'quote before a formula is removed' => ['\'=1+1', '=1+1'];
        yield 'quote before a phone number is removed' => ['\'+49 30 123456', '+49 30 123456'];
        yield 'one of two quotes before a formula is removed' => ['\'\'=1+1', '\'=1+1'];
        yield 'quote before a plain number stays' => ['\'-2', '\'-2'];
        yield 'quote before text stays' => ['\'Main Street 1', '\'Main Street 1'];
        yield 'single quote stays' => ['\'', '\''];
        yield 'formula without a quote stays' => ['=1+1', '=1+1'];
        yield 'null stays' => [null, null];
    }
}
