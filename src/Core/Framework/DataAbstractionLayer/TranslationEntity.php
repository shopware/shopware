<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;

#[Package('framework')]
class TranslationEntity extends Entity
{
    protected string $languageId;

    protected ?LanguageEntity $language = null;

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }
}
