<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\Locale\LocaleCollection;

/**
 * @extends EntityCollection<LanguageEntity>
 */
class LanguageCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getParentIds(): array
    {
        return $this->fmap(function (LanguageEntity $language) {
            return $language->getParentId();
        });
    }

    public function filterByParentId(string $id): LanguageCollection
    {
        return $this->filter(function (LanguageEntity $language) use ($id) {
            return $language->getParentId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getLocaleIds(): array
    {
        return $this->fmap(function (LanguageEntity $language) {
            return $language->getLocaleId();
        });
    }

    public function filterByLocaleId(string $id): LanguageCollection
    {
        return $this->filter(function (LanguageEntity $language) use ($id) {
            return $language->getLocaleId() === $id;
        });
    }

    public function getLocales(): LocaleCollection
    {
        return new LocaleCollection(
            $this->fmap(function (LanguageEntity $language) {
                return $language->getLocale();
            })
        );
    }

    public function getApiAlias(): string
    {
        return 'language_collection';
    }

    protected function getExpectedClass(): string
    {
        return LanguageEntity::class;
    }
}
