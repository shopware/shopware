<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class TranslationWriteResult
{
    /**
     * @param string[] $englishLanguages
     * @param string[] $germanLanguages
     */
    public function __construct(
        private readonly array $englishLanguages,
        private readonly array $germanLanguages
    ) {
    }

    /**
     * @return array<string>
     */
    public function getEnglishLanguages(): array
    {
        return $this->englishLanguages;
    }

    /**
     * @return array<string>
     */
    public function getGermanLanguages(): array
    {
        return $this->germanLanguages;
    }

    public function hasWrittenEnglishTranslations(): bool
    {
        return \count($this->englishLanguages) > 0;
    }

    public function hasWrittenGermanTranslations(): bool
    {
        return \count($this->getGermanLanguages()) > 0;
    }
}
