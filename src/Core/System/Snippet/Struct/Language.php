<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Snippet\SnippetException;
use Symfony\Component\Intl\Locales;

#[Package('discovery')]
class Language extends Struct
{
    public function __construct(
        public readonly string $locale,
        public readonly string $name,
    ) {
        $this->validateLocale($locale);
    }

    private function validateLocale(string $locale): void
    {
        if (str_contains($locale, '-')) {
            // Symfony expects underscores instead of hyphens in locale identifiers
            $locale = str_replace('-', '_', $locale);
        }

        if (!Locales::exists($locale)) {
            throw SnippetException::localeDoesNotExist($locale);
        }
    }
}
