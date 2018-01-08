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

    public function getTranslationIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getTranslations(): LocaleTranslationBasicCollection
    {
        $collection = new LocaleTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getShopIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShops()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getShops(): ShopBasicCollection
    {
        $collection = new ShopBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getShops()->getElements());
        }

        return $collection;
    }

    public function getUserIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getUsers()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
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
