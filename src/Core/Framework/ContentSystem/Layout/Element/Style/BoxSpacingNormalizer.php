<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * Canonicalises one box-spacing value into the explicit four-part CSS form a write stores
 * (`top right bottom left`): arbitrary CSS shorthand is parsed into four sides, each side gets `px`
 * appended when it is a unitless number, and an omitted side becomes `0`. A value with no side input
 * at all, and one whose four sides all normalise to `0`, canonicalise to the empty string — which
 * ElementStyleNormalizer then drops.
 *
 * Idempotent: the four-part form re-parses to the same four sides.
 *
 * @internal
 *
 * @phpstan-type BoxSpacingSides array{top: string, right: string, bottom: string, left: string}
 */
#[Package('framework')]
final class BoxSpacingNormalizer
{
    private const PLAIN_NUMBER_PATTERN = '/^-?(\d+(\.\d+)?|\.\d+)$/D';

    private const PLAIN_NUMBER_WITH_PX_PATTERN = '/^(-?(\d+(\.\d+)?|\.\d+))px$/iD';

    private const CSS_KEYWORD_PATTERN = '/^(auto|inherit|initial|unset|revert)$/iD';

    private const HAS_CSS_UNIT_PATTERN = '/^-?(\d+(\.\d+)?|\.\d+)[a-z%]+$/iD';

    private const EMPTY_SIDES = ['top' => '', 'right' => '', 'bottom' => '', 'left' => ''];

    /**
     * The ECMAScript WhiteSpace ∪ LineTerminator set — exactly what `String.prototype.trim` strips and what
     * the regular-expression class `\s` matches in the reference: TAB, LF, VT, FF, CR, the Unicode `Zs`
     * category (SPACE, NBSP, OGHAM SPACE MARK, EN QUAD…HAIR SPACE, NARROW NBSP, MEDIUM MATHEMATICAL SPACE,
     * IDEOGRAPHIC SPACE), LINE/PARAGRAPH SEPARATOR, and the BOM.
     *
     * Neither PHP primitive covers it, and the two do not even cover the same ASCII: `trim()`'s default list
     * (" \t\n\r\0\x0B") takes NUL but not the form feed, while PCRE's `\s` takes the form feed but not NUL. So
     * a `trim()` and a `preg_split('/\s+/')` in the same class disagree with each other before Unicode is
     * considered at all. One class, used by both, is what keeps them in step and in step with the reference.
     */
    private const WHITESPACE = '\x{0009}\x{000A}\x{000B}\x{000C}\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';

    private const TRIM_PATTERN = '/^[' . self::WHITESPACE . ']+|[' . self::WHITESPACE . ']+$/uD';

    private const WHITESPACE_RUN_PATTERN = '/[' . self::WHITESPACE . ']+/u';

    public function normalizeCssValue(string|int|float|bool $value): string
    {
        if (\is_int($value) || \is_float($value)) {
            return $this->serializeExplicit($this->parse($this->stringify($value)));
        }

        $trimmed = $this->trimWhitespace($this->stringify($value));

        if ($trimmed === '') {
            return '';
        }

        return $this->serializeExplicit($this->parse($trimmed));
    }

    private function stringify(string|int|float|bool $value): string
    {
        if (\is_bool($value)) {
            // The reference stringifies with JS String(), which yields "true"/"false". PHP's cast would
            // yield "1"/"" and silently erase a false, so the write constraints would never see it.
            return $value ? 'true' : 'false';
        }

        if (\is_float($value)) {
            return $this->stringifyFloat($value);
        }

        // Integers need no help: PHP's cast and JS String() both render the exact decimal form, with no
        // separator and no exponent, over the whole range an integer-valued JS number can carry.
        return (string) $value;
    }

    /**
     * PHP's float-to-string, pulled back onto what JS String() would have produced for the same number.
     */
    private function stringifyFloat(float $value): string
    {
        // JS String(-0) is "0"; PHP's cast yields "-0". Not cosmetic: "0" normalises every side to '0', the
        // all-zero early return fires, and the value is dropped — while "-0" matches PLAIN_NUMBER_PATTERN,
        // gains px, and is stored as "-0px -0px -0px -0px". Opposite outcomes. Since -0.0 === 0.0, this one
        // branch catches the negative zero along with the positive one, and both want the same "0".
        if ($value === 0.0) {
            return '0';
        }

        // JS switches to exponent notation only from 1e21 upwards; PHP's cast switches as soon as a value
        // needs more digits than the `precision` ini setting, so `(string) 1.0E+20` is "1.0E+20", matches
        // none of the four patterns, and is emitted verbatim with no unit where the reference emits
        // "100000000000000000000px". Below the JS threshold a whole-valued float is an exact integer, so
        // print that expansion instead.
        if (abs($value) < 1e21 && $value === floor($value)) {
            return number_format($value, 0, '.', '');
        }

        // Residual divergence, deliberately not chased: PHP's float-to-string obeys the `precision` ini
        // setting while JS uses the shortest representation that round-trips, so a fractional value needing
        // more digits than PHP's precision still stringifies differently — as does a magnitude at or beyond
        // the exponent thresholds, where both sides use exponent notation but spell it differently.
        //
        // NAN and INF get no branch: neither can arrive from a JSON decode. Were one to arrive anyway, the
        // reference's Number.isFinite guard would route it through String(), giving "NaN"/"Infinity" where
        // PHP gives "NAN"/"INF" — a known divergence on an unreachable path.
        return (string) $value;
    }

    /**
     * @return BoxSpacingSides
     */
    private function parse(string $value): array
    {
        if ($value === '') {
            return self::EMPTY_SIDES;
        }

        // Split twice, in this order: first on a single literal space, taken only at exactly four parts,
        // then on runs of whitespace. The two disagree for input with repeated spaces, and the reference
        // resolves that disagreement in favour of the first.
        $explicitParts = explode(' ', $value);

        if (\count($explicitParts) === 4) {
            return $this->toInputSides($explicitParts[0], $explicitParts[1], $explicitParts[2], $explicitParts[3]);
        }

        $normalized = $this->trimWhitespace($value);

        if ($normalized === '') {
            return self::EMPTY_SIDES;
        }

        $parts = $this->splitOnWhitespace($normalized);

        $first = $parts[0];
        $second = $parts[1] ?? '';
        $third = $parts[2] ?? '';
        $fourth = $parts[3] ?? '';

        if (\count($parts) === 1) {
            return $this->toInputSides($first, $first, $first, $first);
        }

        if (\count($parts) === 2) {
            return $this->toInputSides($first, $second, $first, $second);
        }

        if (\count($parts) === 3) {
            return $this->toInputSides($first, $second, $third, $second);
        }

        return $this->toInputSides($first, $second, $third, $fourth);
    }

    /**
     * Strips leading and trailing ECMAScript whitespace, the way `String.prototype.trim` does.
     */
    private function trimWhitespace(string $value): string
    {
        $trimmed = preg_replace(self::TRIM_PATTERN, '', $value);

        if ($trimmed === null) {
            throw ContentSystemException::boxSpacingTokenizationFailed('trim', $value, preg_last_error_msg());
        }

        return $trimmed;
    }

    /**
     * Splits on runs of ECMAScript whitespace, the way `String.prototype.split(/\s+/)` does.
     *
     * @return non-empty-list<string>
     */
    private function splitOnWhitespace(string $value): array
    {
        $parts = preg_split(self::WHITESPACE_RUN_PATTERN, $value);

        // Fail hard rather than substituting a plausible-looking split: a substituted result is
        // indistinguishable from a real one, so a PCRE failure would be stored as if it were the authored
        // value. `false` is the documented error return; the empty array cannot occur with these flags and
        // is rejected on the same grounds instead of being left to an undefined offset 0.
        if ($parts === false || $parts === []) {
            throw ContentSystemException::boxSpacingTokenizationFailed('split', $value, preg_last_error_msg());
        }

        return $parts;
    }

    /**
     * @return BoxSpacingSides
     */
    private function toInputSides(string $top, string $right, string $bottom, string $left): array
    {
        return [
            'top' => $this->formatSide($top),
            'right' => $this->formatSide($right),
            'bottom' => $this->formatSide($bottom),
            'left' => $this->formatSide($left),
        ];
    }

    /**
     * Strips the `px` this class appends, so a re-parse sees the side exactly as it was authored.
     * A user-supplied unit (`%`, `rem`, …) is kept.
     */
    private function formatSide(string $value): string
    {
        $trimmed = $this->trimWhitespace($value);

        if ($trimmed === '') {
            return '';
        }

        if (preg_match(self::PLAIN_NUMBER_WITH_PX_PATTERN, $trimmed, $matches) === 1) {
            return $matches[1];
        }

        return $trimmed;
    }

    /**
     * @param BoxSpacingSides $sides
     */
    private function serializeExplicit(array $sides): string
    {
        $hasAnyInput = false;

        foreach ($sides as $side) {
            if ($this->trimWhitespace($side) !== '') {
                $hasAnyInput = true;

                break;
            }
        }

        if (!$hasAnyInput) {
            return '';
        }

        $top = $this->normalizeSide($sides['top']);
        $right = $this->normalizeSide($sides['right']);
        $bottom = $this->normalizeSide($sides['bottom']);
        $left = $this->normalizeSide($sides['left']);

        if ($top === '0' && $right === '0' && $bottom === '0' && $left === '0') {
            return '';
        }

        // The write path always serialises explicitly, so the reference's collapsing forms (a single
        // value for four equal sides, the linked two- and three-part forms) are unreachable here and
        // are deliberately not reproduced.
        return $top . ' ' . $right . ' ' . $bottom . ' ' . $left;
    }

    private function normalizeSide(string $value): string
    {
        $trimmed = $this->trimWhitespace($value);

        if ($trimmed === '') {
            return '0';
        }

        return $this->normalizeUnit($trimmed);
    }

    private function normalizeUnit(string $value): string
    {
        $trimmed = $this->trimWhitespace($value);

        if ($trimmed === '' || $trimmed === '0') {
            return $trimmed;
        }

        if (preg_match(self::CSS_KEYWORD_PATTERN, $trimmed) === 1) {
            return strtolower($trimmed);
        }

        if (str_contains($trimmed, '(') || preg_match(self::HAS_CSS_UNIT_PATTERN, $trimmed) === 1) {
            return $trimmed;
        }

        if (preg_match(self::PLAIN_NUMBER_PATTERN, $trimmed) === 1) {
            return $trimmed . 'px';
        }

        return $trimmed;
    }
}
