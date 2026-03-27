<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class TranslationWriteResult
{
    /**
     * @param list<string> $englishLanguages
     * @param list<string> $germanLanguages
     */
    public function __construct(
        private readonly array $englishLanguages,
        private readonly array $germanLanguages
    ) {
    }

    /**
     * @return list<string>
     */
    public function getEnglishLanguages(): array
    {
        return $this->englishLanguages;
    }

    /**
     * @return list<string>
     */
    public function getGermanLanguages(): array
    {
        return $this->germanLanguages;
    }

    public function hasWrittenEnglishTranslations(): bool
    {
        return $this->englishLanguages !== [];
    }

    public function hasWrittenGermanTranslations(): bool
    {
        return $this->getGermanLanguages() !== [];
    }
}
