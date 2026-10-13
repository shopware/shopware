<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Garan\GaranLabelTextFitter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelTextFitter::class)]
class GaranLabelTextFitterTest extends TestCase
{
    private const LABEL_TEMPLATE = __DIR__ . '/../../../../../../src/Core/Framework/Resources/views/garan/label.svg.twig';

    private const NESTED_LABEL_TEMPLATE = __DIR__ . '/../../../../../../src/Core/Framework/Resources/views/garan/nested-label.svg.twig';

    // the geometry the two templates pass in, asserted against the artwork by the last test below
    private const MANUFACTURER = [190.43, 9.0, 0.0];
    private const PRODUCT_NUMBER = [66.22, 9.0, 0.0];
    private const GUARANTEE = [116.4, 80.0, -0.03];
    private const NESTED_GUARANTEE = [60.48, 41.56, -0.02];

    /**
     * @param array{float, float, float} $box
     */
    #[DataProvider('durationsThatFitProvider')]
    public function testDurationInsideItsBoxKeepsItsPrescribedSpacing(string $duration, array $box): void
    {
        static::assertNull(GaranLabelTextFitter::fitDurationTextLength($duration, ...$box));
    }

    public static function durationsThatFitProvider(): \Generator
    {
        yield 'a two digit duration is what the calendar symbol leaves room for' => ['25', self::GUARANTEE];
        yield 'the widest two digit duration still clears the calendar symbol' => ['44', self::GUARANTEE];
        yield 'a single digit duration' => ['3', self::GUARANTEE];
        yield 'a two digit duration on the nested label' => ['25', self::NESTED_GUARANTEE];
        yield 'a single digit duration on the nested label' => ['3', self::NESTED_GUARANTEE];
    }

    /**
     * @param array{float, float, float} $box
     */
    #[DataProvider('durationsThatOverflowProvider')]
    public function testDurationWiderThanItsBoxIsFittedToIt(string $duration, array $box, float $expected): void
    {
        static::assertSame($expected, GaranLabelTextFitter::fitDurationTextLength($duration, ...$box));
    }

    public static function durationsThatOverflowProvider(): \Generator
    {
        yield 'a half year duration already runs into the calendar symbol' => ['2,5', self::GUARANTEE, 114.9];
        yield 'the duration from the issue report' => ['11,5', self::GUARANTEE, 114.9];
        yield 'the widest duration the formatter can produce for 306 months' => ['25,5', self::GUARANTEE, 114.9];
        yield 'a three digit duration' => ['100', self::GUARANTEE, 114.9];
        yield 'a half year duration on the nested label' => ['11,5', self::NESTED_GUARANTEE, 58.98];
        yield 'the worst case duration on the nested label' => ['25,5', self::NESTED_GUARANTEE, 58.98];
    }

    /**
     * @param array{float, float, float} $box
     */
    #[DataProvider('textThatFitsProvider')]
    public function testTextInsideItsBoxKeepsItsPrescribedSpacing(string $value, array $box): void
    {
        static::assertNull(GaranLabelTextFitter::fitTextLength($value, ...$box));
    }

    public static function textThatFitsProvider(): \Generator
    {
        yield 'a short brand' => ['Acme', self::MANUFACTURER];
        yield 'a brand that reaches most of its column' => ['Siemens Hausgeräte GmbH', self::MANUFACTURER];
        yield 'a typical model identifier' => ['ACME-123', self::PRODUCT_NUMBER];
        yield 'a model identifier that nearly fills its column' => ['SW10000.1', self::PRODUCT_NUMBER];
        yield 'narrow characters are not overcharged' => ['llllllllllllllll', self::PRODUCT_NUMBER];
    }

    /**
     * @param array{float, float, float} $box
     */
    #[DataProvider('textThatOverflowsProvider')]
    public function testTextWiderThanItsBoxIsFittedToIt(string $value, array $box, float $expected): void
    {
        static::assertSame($expected, GaranLabelTextFitter::fitTextLength($value, ...$box));
    }

    public static function textThatOverflowsProvider(): \Generator
    {
        yield 'a brand long enough to reach the model identifier column' => ['Shopware Lebensmittel und Nahrungsmittel GmbH', self::MANUFACTURER, 188.93];
        yield 'the brand from the issue report' => ['Shopware Lebensmittel und Nahrungsmittel GmbH GmbH', self::MANUFACTURER, 188.93];
        yield 'a model identifier long enough to run off the label' => ['1101101101101101', self::PRODUCT_NUMBER, 64.72];
        yield 'a long model identifier' => ['MODEL-IDENTIFIER-2026', self::PRODUCT_NUMBER, 64.72];
    }

    public function testMissingValuesAreNotFitted(): void
    {
        static::assertNull(GaranLabelTextFitter::fitTextLength(null, ...self::MANUFACTURER));
        static::assertNull(GaranLabelTextFitter::fitTextLength('', ...self::MANUFACTURER));
        static::assertNull(GaranLabelTextFitter::fitDurationTextLength(null, ...self::GUARANTEE));
        static::assertNull(GaranLabelTextFitter::fitDurationTextLength('', ...self::GUARANTEE));
    }

    public function testCharactersOutsideTheMetricTablesAreChargedAFullEm(): void
    {
        // Eight full ems exceed the 7.2 em the model identifier column is wide, while the same
        // number of measured ASCII characters stays well inside it.
        static::assertSame(64.72, GaranLabelTextFitter::fitTextLength('相机相机相机相机', ...self::PRODUCT_NUMBER));
        static::assertNull(GaranLabelTextFitter::fitTextLength('AB-12345', ...self::PRODUCT_NUMBER));
    }

    public function testMultibyteValuesAreMeasuredPerCharacterRatherThanPerByte(): void
    {
        // Six two-byte characters must not be charged as twelve.
        static::assertNull(GaranLabelTextFitter::fitTextLength('ÄÖÜäöü', ...self::PRODUCT_NUMBER));
    }

    /**
     * The widths the templates pass in are distances between artwork coordinates. If the artwork
     * moves, they and the clip paths have to be recalculated.
     */
    public function testTheWidthsTheTemplatesPassInStillMatchTheArtwork(): void
    {
        $label = file_get_contents(self::LABEL_TEMPLATE);
        static::assertIsString($label);

        // text anchors
        static::assertStringContainsString('transform="translate(6.32 74.52)"', $label);
        static::assertStringContainsString('transform="translate(196.75 74.52)"', $label);
        static::assertStringContainsString('transform="translate(5.07 150.57)"', $label);
        // the calendar symbol beside the duration, and the rule that sets the right text margin
        static::assertStringContainsString('x="121.47" y="127.8"', $label);
        static::assertStringContainsString('x1="6.32" y1="61.34" x2="262.97"', $label);
        // the widths handed to the filter, and the clip path per editable field
        static::assertStringContainsString('sw_garan_label_text_length(190.43, 9)', $label);
        static::assertStringContainsString('sw_garan_label_text_length(66.22, 9)', $label);
        static::assertStringContainsString('sw_garan_label_duration_text_length(116.4, 80, -0.03)', $label);
        static::assertStringContainsString('<rect x="6.32" y="62" width="190.43"', $label);
        static::assertStringContainsString('<rect x="196.75" y="62" width="66.22"', $label);
        static::assertStringContainsString('<rect x="5.07" y="70" width="116.4"', $label);

        $nested = file_get_contents(self::NESTED_LABEL_TEMPLATE);
        static::assertIsString($nested);

        static::assertStringContainsString('transform="translate(10.39 46.65)"', $nested);
        static::assertStringContainsString('x="70.87" y="34.81"', $nested);
        static::assertStringContainsString('sw_garan_label_duration_text_length(60.48, 41.56, -0.02)', $nested);
        static::assertStringContainsString('<rect x="10.39" y="4" width="60.48"', $nested);
    }
}
