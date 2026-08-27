<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BoxSpacingNormalizer::class)]
class BoxSpacingNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string|int|float|bool, 1: string}>
     */
    public static function cssValueProvider(): iterable
    {
        yield 'unitless integer becomes four px sides' => [20, '20px 20px 20px 20px'];
        yield 'unitless float becomes four px sides' => [1.5, '1.5px 1.5px 1.5px 1.5px'];
        yield 'unitless numeric string becomes four px sides' => ['30', '30px 30px 30px 30px'];
        yield 'single px value becomes four px sides' => ['30px', '30px 30px 30px 30px'];
        yield 'percentage unit is preserved' => ['5%', '5% 5% 5% 5%'];
        yield 'rem unit is preserved' => ['1.5rem', '1.5rem 1.5rem 1.5rem 1.5rem'];
        yield 'two-value shorthand expands vertically and horizontally' => ['8px 16px', '8px 16px 8px 16px'];
        yield 'three-value shorthand mirrors the horizontal side' => ['12px 8px 4px', '12px 8px 4px 8px'];
        yield 'asymmetric four-value form is kept verbatim' => ['20px 40px 20px 40px', '20px 40px 20px 40px'];
        yield 'explicit zero sides survive the four-value form' => ['20px 0 20px 0', '20px 0 20px 0'];
        yield 'css keyword is kept' => ['auto', 'auto auto auto auto'];
        yield 'css keyword is lower-cased' => ['AUTO', 'auto auto auto auto'];
        yield 'parenthesised expression is passed through' => ['calc(100%-20px)', 'calc(100%-20px) calc(100%-20px) calc(100%-20px) calc(100%-20px)'];
        yield 'all-zero shorthand collapses to unset' => ['0', ''];
        yield 'empty string stays unset' => ['', ''];
        yield 'whitespace-only string stays unset' => ['   ', ''];
        yield 'boolean is stringified the way the reference does' => [false, 'false false false false'];
        yield 'negative zero is dropped, not stored as a negative px side' => [-0.0, ''];
        yield 'whole float past PHP precision expands instead of going exponential' => [
            1.0E+20,
            '100000000000000000000px 100000000000000000000px 100000000000000000000px 100000000000000000000px',
        ];
        yield 'surrounding non-breaking spaces are trimmed' => ["\u{00A0}20px\u{00A0}", '20px 20px 20px 20px'];
        yield 'a non-breaking space separates two shorthand values' => ["8px\u{00A0}16px", '8px 16px 8px 16px'];
        yield 'form feeds are trimmed and split consistently' => ["\x0C8px\x0C16px\x0C", '8px 16px 8px 16px'];
    }

    #[DataProvider('cssValueProvider')]
    #[TestDox('normalizes a box-spacing value to the explicit four-part form')]
    public function testNormalizeCssValueProducesTheExplicitFourPartForm(string|int|float|bool $value, string $expected): void
    {
        static::assertSame($expected, (new BoxSpacingNormalizer())->normalizeCssValue($value));
    }

    #[DataProvider('cssValueProvider')]
    #[TestDox('re-normalizing an already normalized box-spacing value changes nothing')]
    public function testNormalizeCssValueIsIdempotent(string|int|float|bool $value, string $expected): void
    {
        $normalizer = new BoxSpacingNormalizer();

        static::assertSame($expected, $normalizer->normalizeCssValue($normalizer->normalizeCssValue($value)));
    }

    #[TestDox('reads an interior run of spaces as four sides, two of them omitted and zeroed')]
    public function testRepeatedSpacesTakeTheLiteralSingleSpaceSplit(): void
    {
        // "8px   16px" splits into exactly four parts on a single literal space, so the reference takes
        // that branch instead of the whitespace-run branch it takes for "8px 16px" — and the two empty
        // parts become explicit zero sides.
        static::assertSame('8px 0 0 16px', (new BoxSpacingNormalizer())->normalizeCssValue('8px   16px'));
    }

    #[TestDox('trims surrounding whitespace before the literal-space split decides the branch')]
    public function testTrimsBeforeSplitting(): void
    {
        // Trailing whitespace would otherwise make this a four-part split; it is trimmed off first, so
        // the two-value shorthand branch applies instead.
        static::assertSame('20px 20px 20px 20px', (new BoxSpacingNormalizer())->normalizeCssValue('20px  20px '));
    }

    #[TestDox('throws instead of substituting a split when the pattern cannot run on the value')]
    public function testPcreFailureThrowsInsteadOfSubstitutingAResult(): void
    {
        // A lone continuation-less 0xC3 is not valid UTF-8, so every `u`-modified pattern in the normalizer
        // fails on it. The entry trim is the first one reached, so it is the operation that reports.
        $value = "\xC3\x28";

        $this->expectExceptionObject(ContentSystemException::boxSpacingTokenizationFailed(
            'trim',
            $value,
            'Malformed UTF-8 characters, possibly incorrectly encoded',
        ));

        (new BoxSpacingNormalizer())->normalizeCssValue($value);
    }
}
