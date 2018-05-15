<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\System\Currency\Collection\CurrencyBasicCollection;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Locale\Collection\LocaleBasicCollection;
use Shopware\Api\Shop\Struct\ShopBasicStruct;

class ShopBasicCollection extends EntityCollection
{
    /**
     * @var ShopBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ShopBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ShopBasicStruct
    {
        return parent::current();
    }

    public function getTemplateIds(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getTemplateId();
        });
    }

    public function filterByTemplateId(string $id): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($id) {
            return $shop->getTemplateId() === $id;
        });
    }

    public function getDocumentTemplateIds(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getDocumentTemplateId();
        });
    }

    public function filterByDocumentTemplateId(string $id): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($id) {
            return $shop->getDocumentTemplateId() === $id;
        });
    }

    public function getCategoryIds(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getCategoryId();
        });
    }

    public function filterByCategoryId(string $id): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($id) {
            return $shop->getCategoryId() === $id;
        });
    }

    public function getLocaleIds(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getLocaleId();
        });
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($id) {
            return $shop->getLocaleId() === $id;
        });
    }

    public function getCurrencyIds(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($id) {
            return $shop->getCurrencyId() === $id;
        });
    }

    public function getCustomerGroupIds(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getCustomerGroupId();
        });
    }

    public function filterByCustomerGroupId(string $id): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($id) {
            return $shop->getCustomerGroupId() === $id;
        });
    }

    public function getFallbackTranslationIds(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getFallbackTranslationId();
        });
    }

    public function filterByFallbackTranslationId(string $id): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($id) {
            return $shop->getFallbackTranslationId() === $id;
        });
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($id) {
            return $shop->getPaymentMethodId() === $id;
        });
    }

    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($id) {
            return $shop->getShippingMethodId() === $id;
        });
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (ShopBasicStruct $shop) {
            return $shop->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (ShopBasicStruct $shop) use ($id) {
            return $shop->getCountryId() === $id;
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
