<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final class SnippetPatterns
{
    /**
     * Locale pattern based on BCP 47,
     * restricted to ISO 639-1 (2-letter) language codes.
     * Excludes 3-letter prefixes like `ger` or `eng`.
     */
    public const LOCALE_PATTERN =
        '(?P<locale>' .
        '(?P<language>[a-z]{2})' .              // ISO 639-1 language prefix
        '(?:[_-](?P<script>[A-Z][a-z]{3}))?' .  // optional script (Hant, Latn, Cyrl)
        '(?:[_-](?P<region>[A-Z]{2}|\d{3}))?' . // optional region (DE, US, 419)
        ')';

    public const CORE_SNIPPET_FILE_PATTERN =
        '/^(?P<domain>.+?)\.' .                 // domain (e.g. messages, storefront, swag-cms-extensions etc.)
        self::LOCALE_PATTERN .                  // locale (e.g. en-GB, de, zh-Hant-TW)
        '(?:\.(?P<isBase>base))?\.json$/';      // optional "base" suffix and .json file extension

    public const ADMIN_SNIPPET_FILE_PATTERN = '/^' . self::LOCALE_PATTERN . '\.json$/';

    public const COMPLETE_LOCALE_PATTERN = '/^' . self::LOCALE_PATTERN . '$/';

    private function __construct()
    {
    }
}
