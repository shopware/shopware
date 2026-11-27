<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Util;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class FormulaEscaper
{
    /**
     * Characters that make a spreadsheet application read a cell as a formula, including the full-width variants.
     *
     * @see https://owasp.org/www-community/attacks/CSV_Injection
     */
    private const TRIGGER_CHARACTERS = ['=', '+', '-', '@', "\t", "\r", "\n", '＝', '＋', '－', '＠'];

    private const QUOTE = '\'';

    /**
     * Prefixes a value that starts with a formula trigger with a single quote, so a spreadsheet application shows it as text.
     */
    public static function escape(mixed $value): mixed
    {
        if (!\is_string($value) || !self::needsQuote($value)) {
            return $value;
        }

        return self::QUOTE . $value;
    }

    /**
     * Removes the quote that escape() added, so an exported file imports with the original values.
     */
    public static function unescape(mixed $value): mixed
    {
        if (!\is_string($value) || !str_starts_with($value, self::QUOTE)) {
            return $value;
        }

        $unquoted = substr($value, 1);

        return self::needsQuote($unquoted) ? $unquoted : $value;
    }

    private static function needsQuote(string $value): bool
    {
        // A value that already starts with quotes gets one more, so that unescape() can tell it from an escaped value.
        $value = ltrim($value, self::QUOTE);
        $firstCharacter = mb_substr($value, 0, 1);

        if (!\in_array($firstCharacter, self::TRIGGER_CHARACTERS, true)) {
            return false;
        }

        // A plain number such as a negative stock cannot hold a formula. Leading whitespace still counts as a trigger.
        return !(\in_array($firstCharacter, ['+', '-'], true) && is_numeric($value));
    }
}
