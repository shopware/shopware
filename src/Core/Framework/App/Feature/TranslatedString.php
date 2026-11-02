<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Feature;

use Shopware\Core\Framework\Log\Package;

/**
 * A value translated per locale (locale code => value).
 *
 * @internal
 */
#[Package('framework')]
final readonly class TranslatedString
{
    private const FALLBACK_LOCALE = 'en-GB';

    /**
     * @param array<string, string> $translations locale code => value
     */
    public function __construct(private array $translations)
    {
    }

    /**
     * The value for the locale; when it is not translated, the en-GB value, otherwise the first one given.
     */
    public function forLocale(string $locale): ?string
    {
        return $this->translations[$locale]
            ?? $this->translations[self::FALLBACK_LOCALE]
            ?? array_values($this->translations)[0]
            ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->translations;
    }
}
