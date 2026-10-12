<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Framework\Log\Package;

/**
 * Measures an editable GARAN label value against the space the artwork leaves it.
 *
 * Returns `null` while the value still fits at its prescribed size, and the `textLength` to squeeze
 * it into once it does not. Callers pass the clear width straight from the artwork - the distance
 * from the field's text anchor to the fixed element beside it - and this reserves the gutter.
 *
 * @internal
 */
#[Package('inventory')]
class GaranLabelTextFitter
{
    /**
     * Kept between the text and whatever the artwork puts next to it, in viewBox units.
     */
    private const GUTTER = 1.5;

    /**
     * Advances in 1/1000 em, keyed by the characters sharing them.
     *
     * Inter Regular over printable ASCII, quantised up to 1/16 em. Coarse on purpose: the quantised
     * model overestimates a real string by at most ~10%, precise enough to decide whether a value
     * overflows without carrying the full metric table.
     */
    private const ADVANCES_REGULAR = [
        250 => 'ijl',
        313 => ' !\',.:;I',
        375 => '()/[\\]`ft|',
        438 => '1r{}',
        500 => '"-^_',
        563 => '*?aksvxyz',
        625 => '2356789EFJLbcdeghnopqu',
        688 => '#$&+04<=>BKPRSTXYZ~',
        750 => 'ACDGHUV',
        813 => 'NOQ',
        875 => 'w',
        938 => 'Mm',
        1000 => '%@W',
    ];

    /**
     * Inter ExtraBold, exact. `GaranLabelDurationFormatter` only ever emits digits and a comma, so
     * this table is complete for the duration fields.
     */
    private const ADVANCES_EXTRA_BOLD = [
        353 => ',.',
        442 => '1',
        588 => '7',
        638 => '2',
        652 => '5',
        657 => '3',
        662 => '69',
        665 => '8',
        689 => '4',
        692 => '0',
    ];

    /**
     * Anything outside the tables - non-Latin scripts, symbols - is charged a full em.
     */
    private const ADVANCE_FALLBACK = 1000;

    /**
     * For the manufacturer and model identifier fields, set in Inter Regular.
     */
    public static function fitTextLength(?string $value, float $clearWidth, float $fontSize, float $letterSpacing = 0.0): ?float
    {
        return self::fit($value, $clearWidth, $fontSize, $letterSpacing, self::ADVANCES_REGULAR);
    }

    /**
     * For the guarantee duration, set in Inter ExtraBold.
     */
    public static function fitDurationTextLength(?string $value, float $clearWidth, float $fontSize, float $letterSpacing = 0.0): ?float
    {
        return self::fit($value, $clearWidth, $fontSize, $letterSpacing, self::ADVANCES_EXTRA_BOLD);
    }

    /**
     * @param array<int, string> $advances
     */
    private static function fit(?string $value, float $clearWidth, float $fontSize, float $letterSpacing, array $advances): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $usableWidth = $clearWidth - self::GUTTER;

        if (self::widthOf($value, $fontSize, $letterSpacing, $advances) <= $usableWidth) {
            return null;
        }

        return $usableWidth;
    }

    /**
     * @param array<int, string> $advances
     */
    private static function widthOf(string $value, float $fontSize, float $letterSpacing, array $advances): float
    {
        $characters = mb_str_split($value);
        $sum = 0;

        foreach ($characters as $character) {
            $sum += self::advanceOf($character, $advances);
        }

        // Letter spacing is applied after every character, the last one included.
        return ($sum / 1000 + \count($characters) * $letterSpacing) * $fontSize;
    }

    /**
     * @param array<int, string> $advances
     */
    private static function advanceOf(string $character, array $advances): int
    {
        foreach ($advances as $advance => $characters) {
            if (str_contains($characters, $character)) {
                return $advance;
            }
        }

        return self::ADVANCE_FALLBACK;
    }
}
