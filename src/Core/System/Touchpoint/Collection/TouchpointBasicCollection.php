<?php declare(strict_types=1);

namespace Shopware\Core\System\Touchpoint\Collection;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Currency\Collection\CurrencyBasicCollection;
use Shopware\Core\System\Language\Collection\LanguageBasicCollection;
use Shopware\Core\System\Touchpoint\Struct\TouchpointBasicStruct;

class TouchpointBasicCollection extends EntityCollection
{
    /**
     * @var TouchpointBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? TouchpointBasicStruct
    {
        return parent::get($id);
    }

    public function current(): TouchpointBasicStruct
    {
        return parent::current();
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (TouchpointBasicStruct $touchpoint) {
            return $touchpoint->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): TouchpointBasicCollection
    {
        return $this->filter(function (TouchpointBasicStruct $touchpoint) use ($id) {
            return $touchpoint->getLanguageId() === $id;
        });
    }

    public function getCurrencyIds(): array
    {
        return $this->fmap(function (TouchpointBasicStruct $touchpoint) {
            return $touchpoint->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): TouchpointBasicCollection
    {
        return $this->filter(function (TouchpointBasicStruct $touchpoint) use ($id) {
            return $touchpoint->getCurrencyId() === $id;
        });
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (TouchpointBasicStruct $touchpoint) {
            return $touchpoint->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): TouchpointBasicCollection
    {
        return $this->filter(function (TouchpointBasicStruct $touchpoint) use ($id) {
            return $touchpoint->getPaymentMethodId() === $id;
        });
    }

    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (TouchpointBasicStruct $touchpoint) {
            return $touchpoint->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): TouchpointBasicCollection
    {
        return $this->filter(function (TouchpointBasicStruct $touchpoint) use ($id) {
            return $touchpoint->getShippingMethodId() === $id;
        });
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (TouchpointBasicStruct $touchpoint) {
            return $touchpoint->getCountryId();
        });
    }

    public function filterByCountryId(string $id): TouchpointBasicCollection
    {
        return $this->filter(function (TouchpointBasicStruct $touchpoint) use ($id) {
            return $touchpoint->getCountryId() === $id;
        });
    }

    public function getCatalogIds(): array
    {
        return $this->fmap(function (TouchpointBasicStruct $touchpoint) {
            return $touchpoint->getCatalogIds();
        });
    }

    public function filterByCatalogIds(string $id): TouchpointBasicCollection
    {
        return $this->filter(function (TouchpointBasicStruct $touchpoint) use ($id) {
            return $touchpoint->getCatalogIds() === $id;
        });
    }

    public function filterByCurrencyIds(string $id): TouchpointBasicCollection
    {
        return $this->filter(function (TouchpointBasicStruct $touchpoint) use ($id) {
            return $touchpoint->getCurrencyIds() === $id;
        });
    }

    public function filterByLanguageIds(string $id): TouchpointBasicCollection
    {
        return $this->filter(function (TouchpointBasicStruct $touchpoint) use ($id) {
            return $touchpoint->getLanguageIds() === $id;
        });
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (TouchpointBasicStruct $touchpoint) {
                return $touchpoint->getLanguage();
            })
        );
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return new CurrencyBasicCollection(
            $this->fmap(function (TouchpointBasicStruct $touchpoint) {
                return $touchpoint->getCurrency();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return TouchpointBasicStruct::class;
    }
}
