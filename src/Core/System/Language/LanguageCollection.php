<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;


use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Locale\LocaleCollection;

class LanguageCollection extends EntityCollection
{
    /**
     * @var LanguageStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? LanguageStruct
    {
        return parent::get($id);
    }

    public function current(): LanguageStruct
    {
        return parent::current();
    }

    public function getParentIds(): array
    {
        return $this->fmap(function (LanguageStruct $language) {
            return $language->getParentId();
        });
    }

    public function filterByParentId(string $id): LanguageCollection
    {
        return $this->filter(function (LanguageStruct $language) use ($id) {
            return $language->getParentId() === $id;
        });
    }

    public function getLocaleIds(): array
    {
        return $this->fmap(function (LanguageStruct $language) {
            return $language->getLocaleId();
        });
    }

    public function filterByLocaleId(string $id): LanguageCollection
    {
        return $this->filter(function (LanguageStruct $language) use ($id) {
            return $language->getLocaleId() === $id;
        });
    }

    public function getLocaleVersionIds(): array
    {
        return $this->fmap(function (LanguageStruct $language) {
            return $language->getLocaleVersionId();
        });
    }

    public function filterByLocaleVersionId(string $id): LanguageCollection
    {
        return $this->filter(function (LanguageStruct $language) use ($id) {
            return $language->getLocaleVersionId() === $id;
        });
    }

    public function getLocales(): LocaleCollection
    {
        return new LocaleCollection(
            $this->fmap(function (LanguageStruct $language) {
                return $language->getLocale();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return LanguageStruct::class;
    }
}
