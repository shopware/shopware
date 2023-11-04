<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
class StaticTranslator implements TranslatorInterface
{
    /**
     * @param array<string, string> $translations
     */
    public function __construct(
        private readonly array $translations = [],
        private readonly string $locale = 'en-GB'
    ) {
    }

    /**
     * @param array<string, string> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        if (!\array_key_exists($id, $this->translations)) {
            throw new \InvalidArgumentException(sprintf('Translation for "%s" not found', $id));
        }

        return $this->translations[$id];
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
