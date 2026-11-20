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

    /**
     * Prefixes a value that starts with a formula trigger with a single quote, so a spreadsheet application shows it as text.
     */
    public static function escape(mixed $value): mixed
    {
        if (!\is_string($value) || !\in_array(mb_substr($value, 0, 1), self::TRIGGER_CHARACTERS, true)) {
            return $value;
        }

        // A plain number such as a negative stock cannot hold a formula and has to stay importable.
        if (is_numeric($value)) {
            return $value;
        }

        return '\'' . $value;
    }
}
