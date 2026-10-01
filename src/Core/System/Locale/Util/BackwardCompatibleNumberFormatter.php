<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Util;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - reason:behavior-change - will be removed in 6.8.0, invalid locales won't be supported anymore
 *
 * We extend NumberFormatter to make sure that invalid locales are still supported
 * Since php 8.4.0 invalid locales will throw an exception, which leads to a breaking change
 */
#[Package('discovery')]
final class BackwardCompatibleNumberFormatter extends \NumberFormatter
{
    public function __construct(
        string $locale,
        int $style,
        ?string $pattern = null,
    ) {
        if ($locale === '' || LocaleHelper::isLocale($locale)) {
            parent::__construct($locale, $style, $pattern);

            return;
        }

        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            'The locale "' . $locale . '" is no valid PHP locale. Please use a valid locale.'
        );

        parent::__construct('', $style, $pattern);
    }
}
