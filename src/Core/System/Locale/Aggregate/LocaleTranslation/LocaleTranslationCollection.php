<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Aggregate\LocaleTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;


class LocaleTranslationCollection extends EntityCollection
{
    /**
     * @var LocaleTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? LocaleTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): LocaleTranslationStruct
    {
        return parent::current();
    }

    public function getLocaleIds(): array
    {
        return $this->fmap(function (LocaleTranslationStruct $localeTranslation) {
            return $localeTranslation->getLocaleId();
        });
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(function (LocaleTranslationStruct $localeTranslation) use ($id) {
            return $localeTranslation->getLocaleId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (LocaleTranslationStruct $localeTranslation) {
            return $localeTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (LocaleTranslationStruct $localeTranslation) use ($id) {
            return $localeTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return LocaleTranslationStruct::class;
    }
}
