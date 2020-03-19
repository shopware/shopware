<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeCollection;

/**
 * @method void                    add(SalesChannelEntity $entity)
 * @method void                    set(string $key, SalesChannelEntity $entity)
 * @method SalesChannelEntity[]    getIterator()
 * @method SalesChannelEntity[]    getElements()
 * @method SalesChannelEntity|null get(string $key)
 * @method SalesChannelEntity|null first()
 * @method SalesChannelEntity|null last()
 */
class SalesChannelCollection extends EntityCollection
{
    public function getLanguageIds(): array
    {
        return $this->fmap(function (SalesChannelEntity $salesChannel) {
            return $salesChannel->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelEntity $salesChannel) use ($id) {
            return $salesChannel->getLanguageId() === $id;
        });
    }

    public function getCurrencyIds(): array
    {
        return $this->fmap(function (SalesChannelEntity $salesChannel) {
            return $salesChannel->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelEntity $salesChannel) use ($id) {
            return $salesChannel->getCurrencyId() === $id;
        });
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (SalesChannelEntity $salesChannel) {
            return $salesChannel->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelEntity $salesChannel) use ($id) {
            return $salesChannel->getPaymentMethodId() === $id;
        });
    }

    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (SalesChannelEntity $salesChannel) {
            return $salesChannel->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelEntity $salesChannel) use ($id) {
            return $salesChannel->getShippingMethodId() === $id;
        });
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (SalesChannelEntity $salesChannel) {
            return $salesChannel->getCountryId();
        });
    }

    public function filterByCountryId(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelEntity $salesChannel) use ($id) {
            return $salesChannel->getCountryId() === $id;
        });
    }

    public function getTypeIds(): array
    {
        return $this->fmap(function (SalesChannelEntity $salesChannel) {
            return $salesChannel->getTypeId();
        });
    }

    public function filterByTypeId(string $id): SalesChannelCollection
    {
        return $this->filter(function (SalesChannelEntity $salesChannel) use ($id) {
            return $salesChannel->getTypeId() === $id;
        });
    }

    public function getLanguages(): LanguageCollection
    {
        return new LanguageCollection(
            $this->fmap(function (SalesChannelEntity $salesChannel) {
                return $salesChannel->getLanguage();
            })
        );
    }

    public function getCurrencies(): CurrencyCollection
    {
        return new CurrencyCollection(
            $this->fmap(function (SalesChannelEntity $salesChannel) {
                return $salesChannel->getCurrency();
            })
        );
    }

    public function getTypes(): SalesChannelTypeCollection
    {
        return new SalesChannelTypeCollection(
            $this->fmap(function (SalesChannelEntity $salesChannel) {
                return $salesChannel->getType();
            })
        );
    }

    public function getApiAlias(): string
    {
        return 'sales_channel_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelEntity::class;
    }
}
