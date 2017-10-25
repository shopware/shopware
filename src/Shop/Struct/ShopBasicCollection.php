<?php declare(strict_types=1);

namespace Shopware\Shop\Struct;

use Shopware\Currency\Struct\CurrencyBasicCollection;
use Shopware\Framework\Struct\Collection;
use Shopware\Locale\Struct\LocaleBasicCollection;

class ShopBasicCollection extends Collection
{
    /**
     * @var ShopBasicStruct[]
     */
    protected $elements = [];

    public function add(ShopBasicStruct $shop): void
    {
        $key = $this->getKey($shop);
        $this->elements[$key] = $shop;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ShopBasicStruct $shop): void
    {
        parent::doRemoveByKey($this->getKey($shop));
    }

    public function exists(ShopBasicStruct $shop): bool
    {
        return parent::has($this->getKey($shop));
    }

    public function getList(array $uuids): ShopBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ShopBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getUuid();
        });
    }

    public function merge(ShopBasicCollection $collection)
    {
        /** @var ShopBasicStruct $shop */
        foreach ($collection as $shop) {
            if ($this->has($this->getKey($shop))) {
                continue;
            }
            $this->add($shop);
        }
    }

    public function getParentUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getParentUuid();
        });
    }

    public function filterByParentUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getParentUuid() === $uuid;
        });
    }

    public function getTemplateUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getTemplateUuid();
        });
    }

    public function filterByTemplateUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getTemplateUuid() === $uuid;
        });
    }

    public function getDocumentTemplateUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getDocumentTemplateUuid();
        });
    }

    public function filterByDocumentTemplateUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getDocumentTemplateUuid() === $uuid;
        });
    }

    public function getCategoryUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getCategoryUuid();
        });
    }

    public function filterByCategoryUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getCategoryUuid() === $uuid;
        });
    }

    public function getLocaleUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getLocaleUuid();
        });
    }

    public function filterByLocaleUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getLocaleUuid() === $uuid;
        });
    }

    public function getCurrencyUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getCurrencyUuid();
        });
    }

    public function filterByCurrencyUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getCurrencyUuid() === $uuid;
        });
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getCustomerGroupUuid();
        });
    }

    public function filterByCustomerGroupUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getCustomerGroupUuid() === $uuid;
        });
    }

    public function getFallbackLocaleUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getFallbackLocaleUuid();
        });
    }

    public function filterByFallbackLocaleUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getFallbackLocaleUuid() === $uuid;
        });
    }

    public function getPaymentMethodUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getPaymentMethodUuid();
        });
    }

    public function filterByPaymentMethodUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getPaymentMethodUuid() === $uuid;
        });
    }

    public function getShippingMethodUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getShippingMethodUuid();
        });
    }

    public function filterByShippingMethodUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getShippingMethodUuid() === $uuid;
        });
    }

    public function getAreaCountryUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getAreaCountryUuid();
        });
    }

    public function filterByAreaCountryUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getAreaCountryUuid() === $uuid;
        });
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return new CurrencyBasicCollection(
            $this->fmap(function (ShopBasicStruct $shop) {
                return $shop->getCurrency();
            })
        );
    }

    public function getLocales(): LocaleBasicCollection
    {
        return new LocaleBasicCollection(
            $this->fmap(function (ShopBasicStruct $shop) {
                return $shop->getLocale();
            })
        );
    }

    public function sortByPosition(): ShopBasicCollection
    {
        $this->sort(function (ShopBasicStruct $a, ShopBasicStruct $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        return $this;
    }

    public function current(): ShopBasicStruct
    {
        return parent::current();
    }

    protected function getKey(ShopBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
