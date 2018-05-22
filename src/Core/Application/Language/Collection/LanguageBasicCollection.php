<?php declare(strict_types=1);

namespace Shopware\Application\Language\Collection;

use Shopware\Application\Language\Struct\LanguageBasicStruct;
use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\Locale\Collection\LocaleBasicCollection;

class LanguageBasicCollection extends EntityCollection
{
    /**
     * @var LanguageBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? LanguageBasicStruct
    {
        return parent::get($id);
    }

    public function current(): LanguageBasicStruct
    {
        return parent::current();
    }

    public function getParentIds(): array
    {
        return $this->fmap(function (LanguageBasicStruct $language) {
            return $language->getParentId();
        });
    }

    public function filterByParentId(string $id): LanguageBasicCollection
    {
        return $this->filter(function (LanguageBasicStruct $language) use ($id) {
            return $language->getParentId() === $id;
        });
    }

    public function getLocaleIds(): array
    {
        return $this->fmap(function (LanguageBasicStruct $language) {
            return $language->getLocaleId();
        });
    }

    public function filterByLocaleId(string $id): LanguageBasicCollection
    {
        return $this->filter(function (LanguageBasicStruct $language) use ($id) {
            return $language->getLocaleId() === $id;
        });
    }

    public function getLocaleVersionIds(): array
    {
        return $this->fmap(function (LanguageBasicStruct $language) {
            return $language->getLocaleVersionId();
        });
    }

    public function filterByLocaleVersionId(string $id): LanguageBasicCollection
    {
        return $this->filter(function (LanguageBasicStruct $language) use ($id) {
            return $language->getLocaleVersionId() === $id;
        });
    }

    public function getLocales(): LocaleBasicCollection
    {
        return new LocaleBasicCollection(
            $this->fmap(function (LanguageBasicStruct $language) {
                return $language->getLocale();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return LanguageBasicStruct::class;
    }
}
