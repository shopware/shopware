<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\System\Language\LanguageLoaderInterface;
use Symfony\Contracts\Service\ResetInterface;

class LanguageLocaleCodeProvider implements ResetInterface
{
    private LanguageLoaderInterface $languageLoader;

    private array $languages = [];

    public function __construct(LanguageLoaderInterface $languageLoader)
    {
        $this->languageLoader = $languageLoader;
    }

    public function getLocaleForLanguageId(string $languageId): string
    {
        $languages = $this->getLanguages();

        if (!\array_key_exists($languageId, $languages)) {
            throw new LanguageNotFoundException($languageId);
        }

        return $languages[$languageId]['code'];
    }

    public function getLocalesForLanguageIds(array $languageIds): array
    {
        $languages = $this->getLanguages();

        $requestedLanguages = array_intersect_key($languages, array_flip($languageIds));

        return array_column($requestedLanguages, 'code', 'id');
    }

    public function reset(): void
    {
        $this->languages = [];
    }

    private function getLanguages(): array
    {
        if (\count($this->languages) === 0) {
            $this->languages = $this->languageLoader->loadLanguages();
        }

        return $this->languages;
    }
}
