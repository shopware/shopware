<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageLoaderInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @phpstan-import-type LanguageData from \Shopware\Core\System\Language\LanguageLoaderInterface
 */
#[Package('buyers-experience')]
class LanguageLocaleCodeProvider implements ResetInterface
{
    /**
     * @var LanguageData
     */
    private array $languages = [];

    /**
     * @internal
     */
    public function __construct(private readonly LanguageLoaderInterface $languageLoader)
    {
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
        $this->languages = [];
    }

    /**
     * @return LanguageData
     */
    private function getLanguages(): array
    {
        if (\count($this->languages) === 0) {
            $this->languages = $this->resolveParentLanguages(
                $this->languageLoader->loadLanguages()
            );
        }

        return $this->languages;
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
}
