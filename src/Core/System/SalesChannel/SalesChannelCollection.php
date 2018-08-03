<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Language\LanguageCollection;

class SalesChannelCollection extends EntityCollection
{
    /**
     * @var SalesChannelStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? SalesChannelStruct
    {
        return parent::get($id);
    }

    public function current(): SalesChannelStruct
    {
        return parent::current();
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (SalesChannelStruct $salesChannel) {
            return $salesChannel->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelStruct $salesChannel) use ($id) {
            return $salesChannel->getLanguageId() === $id;
        });
    }

    public function getCurrencyIds(): array
    {
        return $this->fmap(function (SalesChannelStruct $salesChannel) {
            return $salesChannel->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelStruct $salesChannel) use ($id) {
            return $salesChannel->getCurrencyId() === $id;
        });
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (SalesChannelStruct $salesChannel) {
            return $salesChannel->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelStruct $salesChannel) use ($id) {
            return $salesChannel->getPaymentMethodId() === $id;
        });
    }

    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (SalesChannelStruct $salesChannel) {
            return $salesChannel->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelStruct $salesChannel) use ($id) {
            return $salesChannel->getShippingMethodId() === $id;
        });
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (SalesChannelStruct $salesChannel) {
            return $salesChannel->getCountryId();
        });
    }

    public function filterByCountryId(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelStruct $salesChannel) use ($id) {
            return $salesChannel->getCountryId() === $id;
        });
    }

    public function getCatalogIds(): array
    {
        return $this->fmap(function (SalesChannelStruct $salesChannel) {
            return $salesChannel->getCatalogIds();
        });
    }

    public function filterByCatalogIds(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelStruct $salesChannel) use ($id) {
            return $salesChannel->getCatalogIds() === $id;
        });
    }

    public function filterByCurrencyIds(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelStruct $salesChannel) use ($id) {
            return $salesChannel->getCurrencyIds() === $id;
        });
    }

    public function filterByLanguageIds(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelStruct $salesChannel) use ($id) {
            return $salesChannel->getLanguageIds() === $id;
        });
    }

    public function getLanguages(): LanguageCollection
    {
        return new LanguageCollection(
            $this->fmap(function (SalesChannelStruct $salesChannel) {
                return $salesChannel->getLanguage();
            })
        );
    }

    public function getCurrencies(): CurrencyCollection
    {
        return new CurrencyCollection(
            $this->fmap(function (SalesChannelStruct $salesChannel) {
                return $salesChannel->getCurrency();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelStruct::class;
    }
}
