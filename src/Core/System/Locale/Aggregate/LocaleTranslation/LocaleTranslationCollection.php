<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Aggregate\LocaleTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LocaleTranslationEntity>
 */
class LocaleTranslationCollection extends EntityCollection
{
    public function getLocaleIds(): array
    {
        return $this->fmap(function (LocaleTranslationEntity $localeTranslation) {
            return $localeTranslation->getLocaleId();
        });
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(function (LocaleTranslationEntity $localeTranslation) use ($id) {
            return $localeTranslation->getLocaleId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (LocaleTranslationEntity $localeTranslation) {
            return $localeTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (LocaleTranslationEntity $localeTranslation) use ($id) {
            return $localeTranslation->getLanguageId() === $id;
        });
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
