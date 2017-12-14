<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Collection;

use Shopware\Api\Locale\Struct\LocaleDetailStruct;
use Shopware\Api\Shop\Collection\ShopBasicCollection;
use Shopware\Api\User\Collection\UserBasicCollection;

class LocaleDetailCollection extends LocaleBasicCollection
{
    /**
     * @var LocaleDetailStruct[]
     */
    protected $elements = [];

    public function getTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getTranslations(): LocaleTranslationBasicCollection
    {
        $collection = new LocaleTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getShopUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShops()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getShops(): ShopBasicCollection
    {
        $collection = new ShopBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getShops()->getElements());
        }

        return $collection;
    }

    public function getUserUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getUsers()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getUsers(): UserBasicCollection
    {
        $collection = new UserBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getUsers()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return LocaleDetailStruct::class;
    }
}
