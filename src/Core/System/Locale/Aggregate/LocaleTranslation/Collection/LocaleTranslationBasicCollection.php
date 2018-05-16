<?php declare(strict_types=1);

namespace Shopware\System\Locale\Aggregate\LocaleTranslation\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\Locale\Aggregate\LocaleTranslation\Struct\LocaleTranslationBasicStruct;

class LocaleTranslationBasicCollection extends EntityCollection
{
    /**
     * @var LocaleTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? LocaleTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): LocaleTranslationBasicStruct
    {
        return parent::current();
    }

    public function getLocaleIds(): array
    {
        return $this->fmap(function (LocaleTranslationBasicStruct $localeTranslation) {
            return $localeTranslation->getLocaleId();
        });
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(function (LocaleTranslationBasicStruct $localeTranslation) use ($id) {
            return $localeTranslation->getLocaleId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (LocaleTranslationBasicStruct $localeTranslation) {
            return $localeTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (LocaleTranslationBasicStruct $localeTranslation) use ($id) {
            return $localeTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return LocaleTranslationBasicStruct::class;
    }
}
