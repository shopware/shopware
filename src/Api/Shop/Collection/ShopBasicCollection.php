<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Api\Currency\Collection\CurrencyBasicCollection;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Locale\Collection\LocaleBasicCollection;
use Shopware\Api\Shop\Struct\ShopBasicStruct;

class ShopBasicCollection extends EntityCollection
{
    /**
     * @var ShopBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ShopBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ShopBasicStruct
    {
        return parent::current();
    }

    public function getParentUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getParentUuid();
        });
    }

    public function filterByParentUuid(string $uuid): self
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

    public function filterByTemplateUuid(string $uuid): self
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

    public function filterByDocumentTemplateUuid(string $uuid): self
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

    public function filterByCategoryUuid(string $uuid): self
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

    public function filterByLocaleUuid(string $uuid): self
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

    public function filterByCurrencyUuid(string $uuid): self
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

    public function filterByCustomerGroupUuid(string $uuid): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getCustomerGroupUuid() === $uuid;
        });
    }

    public function getFallbackTranslationUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getFallbackTranslationUuid();
        });
    }

    public function filterByFallbackTranslationUuid(string $uuid): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getFallbackTranslationUuid() === $uuid;
        });
    }

    public function getPaymentMethodUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getPaymentMethodUuid();
        });
    }

    public function filterByPaymentMethodUuid(string $uuid): self
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

    public function filterByShippingMethodUuid(string $uuid): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getShippingMethodUuid() === $uuid;
        });
    }

    public function getCountryUuids(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getCountryUuid();
        });
    }

    public function filterByCountryUuid(string $uuid): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($uuid) {
            return $shop->getCountryUuid() === $uuid;
        });
    }

    public function getLocales(): LocaleBasicCollection
    {
        return new LocaleBasicCollection(
            $this->fmap(function (ShopBasicStruct $shop) {
                return $shop->getLocale();
            })
        );
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return new CurrencyBasicCollection(
            $this->fmap(function (ShopBasicStruct $shop) {
                return $shop->getCurrency();
            })
        );
    }

    public function sortByPosition(): self
    {
        $this->sort(function (ShopBasicStruct $a, ShopBasicStruct $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        return $this;
    }

    protected function getExpectedClass(): string
    {
        return ShopBasicStruct::class;
    }
}
