<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

class TranslationWriteResult
{
    /**
     * @var string[]
     */
    private $englishLanguages;

    /**
     * @var string[]
     */
    private $germanLanguages;

    public function __construct(array $englishLanguages, array $germanLanguages)
    {
        $this->englishLanguages = $englishLanguages;
        $this->germanLanguages = $germanLanguages;
    }

    /**
     * @return string[]
     */
    public function getEnglishLanguages(): array
    {
        return $this->englishLanguages;
    }

    /**
     * @return string[]
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
