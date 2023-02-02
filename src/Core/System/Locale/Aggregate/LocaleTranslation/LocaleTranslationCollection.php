<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Aggregate\LocaleTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<LocaleTranslationEntity>
 */
#[Package('system-settings')]
class LocaleTranslationCollection extends EntityCollection
{
    public function getLocaleIds(): array
    {
        return $this->fmap(fn (LocaleTranslationEntity $localeTranslation) => $localeTranslation->getLocaleId());
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(fn (LocaleTranslationEntity $localeTranslation) => $localeTranslation->getLocaleId() === $id);
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(fn (LocaleTranslationEntity $localeTranslation) => $localeTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (LocaleTranslationEntity $localeTranslation) => $localeTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'locale_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return LocaleTranslationEntity::class;
    }
}
