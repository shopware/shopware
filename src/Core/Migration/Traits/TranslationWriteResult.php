<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

class TranslationWriteResult
{
    /**
     * @var array<string>
     */
    private $englishLanguages;

    /**
     * @var array<string>
     */
    private $germanLanguages;

    public function __construct(array $englishLanguages, array $germanLanguages)
    {
        $this->englishLanguages = $englishLanguages;
        $this->germanLanguages = $germanLanguages;
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
