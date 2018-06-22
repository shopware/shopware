<?php declare(strict_types=1);

namespace Shopware\Core\System\Touchpoint;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Language\LanguageCollection;

class TouchpointCollection extends EntityCollection
{
    /**
     * @var TouchpointStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? TouchpointStruct
    {
        return parent::get($id);
    }

    public function current(): TouchpointStruct
    {
        return parent::current();
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (TouchpointStruct $touchpoint) {
            return $touchpoint->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): TouchpointCollection
    {
        return $this->filter(function (TouchpointStruct $touchpoint) use ($id) {
            return $touchpoint->getLanguageId() === $id;
        });
    }

    public function getCurrencyIds(): array
    {
        return $this->fmap(function (TouchpointStruct $touchpoint) {
            return $touchpoint->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): TouchpointCollection
    {
        return $this->filter(function (TouchpointStruct $touchpoint) use ($id) {
            return $touchpoint->getCurrencyId() === $id;
        });
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (TouchpointStruct $touchpoint) {
            return $touchpoint->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): TouchpointCollection
    {
        return $this->filter(function (TouchpointStruct $touchpoint) use ($id) {
            return $touchpoint->getPaymentMethodId() === $id;
        });
    }

    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (TouchpointStruct $touchpoint) {
            return $touchpoint->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): TouchpointCollection
    {
        return $this->filter(function (TouchpointStruct $touchpoint) use ($id) {
            return $touchpoint->getShippingMethodId() === $id;
        });
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (TouchpointStruct $touchpoint) {
            return $touchpoint->getCountryId();
        });
    }

    public function filterByCountryId(string $id): TouchpointCollection
    {
        return $this->filter(function (TouchpointStruct $touchpoint) use ($id) {
            return $touchpoint->getCountryId() === $id;
        });
    }

    public function getCatalogIds(): array
    {
        return $this->fmap(function (TouchpointStruct $touchpoint) {
            return $touchpoint->getCatalogIds();
        });
    }

    public function filterByCatalogIds(string $id): TouchpointCollection
    {
        return $this->filter(function (TouchpointStruct $touchpoint) use ($id) {
            return $touchpoint->getCatalogIds() === $id;
        });
    }

    public function filterByCurrencyIds(string $id): TouchpointCollection
    {
        return $this->filter(function (TouchpointStruct $touchpoint) use ($id) {
            return $touchpoint->getCurrencyIds() === $id;
        });
    }

    public function filterByLanguageIds(string $id): TouchpointCollection
    {
        return $this->filter(function (TouchpointStruct $touchpoint) use ($id) {
            return $touchpoint->getLanguageIds() === $id;
        });
    }

    public function getLanguages(): LanguageCollection
    {
        return new LanguageCollection(
            $this->fmap(function (TouchpointStruct $touchpoint) {
                return $touchpoint->getLanguage();
            })
        );
    }

    public function getCurrencies(): CurrencyCollection
    {
        return new CurrencyCollection(
            $this->fmap(function (TouchpointStruct $touchpoint) {
                return $touchpoint->getCurrency();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return TouchpointStruct::class;
    }
}
