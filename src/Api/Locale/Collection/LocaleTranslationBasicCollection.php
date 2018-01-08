<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Locale\Struct\LocaleTranslationBasicStruct;

class LocaleTranslationBasicCollection extends EntityCollection
{
    /**
     * @var LocaleTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? LocaleTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): LocaleTranslationBasicStruct
    {
        return parent::current();
    }

    public function getLocaleUuids(): array
    {
        return $this->fmap(function (LocaleTranslationBasicStruct $localeTranslation) {
            return $localeTranslation->getLocaleUuid();
        });
    }

    public function filterByLocaleUuid(string $uuid): self
    {
        return $this->filter(function (LocaleTranslationBasicStruct $localeTranslation) use ($uuid) {
            return $localeTranslation->getLocaleUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (LocaleTranslationBasicStruct $localeTranslation) {
            return $localeTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): self
    {
        return $this->filter(function (LocaleTranslationBasicStruct $localeTranslation) use ($uuid) {
            return $localeTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return LocaleTranslationBasicStruct::class;
    }
}
