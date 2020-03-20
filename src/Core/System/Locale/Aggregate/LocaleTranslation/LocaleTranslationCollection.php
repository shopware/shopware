<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Aggregate\LocaleTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(LocaleTranslationEntity $entity)
 * @method void                         set(string $key, LocaleTranslationEntity $entity)
 * @method LocaleTranslationEntity[]    getIterator()
 * @method LocaleTranslationEntity[]    getElements()
 * @method LocaleTranslationEntity|null get(string $key)
 * @method LocaleTranslationEntity|null first()
 * @method LocaleTranslationEntity|null last()
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
