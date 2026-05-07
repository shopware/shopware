<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageLoaderInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @phpstan-import-type LanguageData from LanguageLoaderInterface
 */
#[Package('discovery')]
class LanguageLocaleCodeProvider implements ResetInterface
{
    /**
     * @var ?LanguageData
     */
    private ?array $languages = null;

    /**
     * @internal
     */
    public function __construct(private readonly LanguageLoaderInterface $languageLoader)
    {
    }

    public function getLanguageLocalePrefix(string $languageId): string
    {
        return explode('-', $this->getLocaleForLanguageId($languageId))[0];
    }

    public function getLocaleForLanguageId(string $languageId): string
    {
        $languages = $this->getLanguages();

        if (!\array_key_exists($languageId, $languages)) {
            throw LocaleException::languageNotFound($languageId);
        }

        return $languages[$languageId]['code'];
    }

    /**
     * @return string[]
     */
    public function getParentLanguageLocalesForLanguageId(string $languageId): array
    {
        return $this->getParentLanguageCodes($languageId);
    }

    /**
     * @param array<string> $languageIds
     *
     * @return array<string, string>
     */
    public function getLocalesForLanguageIds(array $languageIds): array
    {
        $languages = $this->getLanguages();

        $requestedLanguages = array_intersect_key($languages, array_flip($languageIds));

        return array_column($requestedLanguages, 'code', 'id');
    }

    public function reset(): void
    {
        $this->languages = null;
    }

    /**
     * @return LanguageData
     */
    private function getLanguages(): array
    {
        if ($this->languages !== null) {
            return $this->languages;
        }

        return $this->languages = $this->resolveParentLanguages($this->languageLoader->loadLanguages());
    }

    /**
     * resolves the inherited languages codes, so we have a guaranteed language code for each language id
     * we can't do it in the language loader as other places (e.g. DAL writes) expect that the translation code is unique
     *
     * @param LanguageData $languages
     *
     * @return LanguageData
     */
    private function resolveParentLanguages(array $languages): array
    {
        foreach ($languages as &$language) {
            if ($language['code'] !== null || $language['parentId'] === null) {
                continue;
            }

            $language['code'] = $languages[$language['parentId']]['code'] ?? null;
        }

        return $languages;
    }

    /**
     * @param (string|null)[] $parentLanguageCodes
     *
     * @return string[]
     */
    private function getParentLanguageCodes(string $languageId, array $parentLanguageCodes = []): array
    {
        $languages = $this->getLanguages();

        if (!\array_key_exists($languageId, $languages)) {
            throw LocaleException::languageNotFound($languageId);
        }

        $parentId = $languages[$languageId]['parentId'] ?? null;

        if ($parentId === null) {
            return array_unique(array_filter($parentLanguageCodes));
        }

        $parentLanguageCodes[] = $languages[$parentId]['code'] ?? null;

        return $this->getParentLanguageCodes($parentId, $parentLanguageCodes);
    }
}
