<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Util;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Languages;

#[Package('discovery')]
class LocaleHelper
{
    private const LEGACY_REGION_CODES = [
        'CS',
    ];

    public static function isLocale(string $locale): bool
    {
        try {
            /** @phpstan-ignore new.resultUnused */
            new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        } catch (\ValueError) {
            return false;
        }

        $canonicalized = \Locale::canonicalize($locale);

        if ($canonicalized === null) {
            return false;
        }

        $parsed = \Locale::parseLocale($canonicalized);

        return isset($parsed['language'], $parsed['region'])
            && Languages::exists($parsed['language'])
            && (
                Countries::exists($parsed['region'])
                || \in_array($parsed['region'], LocaleHelper::LEGACY_REGION_CODES, true)
            );
    }
}
