<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Feature;
use Twig\Extension\AbstractExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\TwigFilter;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - will be removed in 6.8.0, invalid locales won't be supported anymore
 *
 * We overwrite the IntlExtension to make sure that invalid locales are still supported
 * Since php 8.4.1 invalid locales will throw an exception, which leads to a breaking change
 */
class BackwardCompatibleIntlExtension extends AbstractExtension
{
    public function __construct(
        private readonly IntlExtension $intlExtension,
    ) {
    }

    public function getFilters(): array
    {
        return [
            // localized formatters
            new TwigFilter('format_currency', [$this, 'formatCurrency']),
            new TwigFilter('format_number', [$this, 'formatNumber']),
            new TwigFilter('format_*_number', [$this, 'formatNumberStyle']),
        ];
    }

    public function formatCurrency($amount, string $currency, array $attrs = [], ?string $locale = null): string
    {
        try {
            new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        } catch (\ValueError) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'The locale "' . $locale . '" passed to "format_currency" twig filter is no valid PHP locale. Please use a valid locale.'
            );

            $locale = null;
        }

        return $this->intlExtension->formatCurrency($amount, $currency, $attrs, $locale);
    }

    public function formatNumber($number, array $attrs = [], string $style = 'decimal', string $type = 'default', ?string $locale = null): string
    {
        try {
            new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        } catch (\ValueError) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'The locale "' . $locale . '" passed to "format_number" twig filter is no valid PHP locale. Please use a valid locale.'
            );

            $locale = null;
        }

        return $this->intlExtension->formatNumber($number, $attrs, $style, $type, $locale);
    }

    public function formatNumberStyle(string $style, $number, array $attrs = [], string $type = 'default', ?string $locale = null): string
    {
        return $this->formatNumber($number, $attrs, $style, $type, $locale);
    }
}
