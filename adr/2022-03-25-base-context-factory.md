---
title: Base context factory
date: 2022-03-25
area: core
tags: [core, sales-channel, performance, cache]
---
Within each store api request (and storefront), the sales channel context must be built. Building the sales channel context is a very resource consuming task for the database, since many DAL objects are now included in the sales channel context. Therefore, a cache for the corresponding service (`Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory`) has already been implemented in the past: `Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory`. However, since the context also contains the customer and the selected shipping address as well as billing address, the context cannot be cached once a customer is logged in:

```php
<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

class CachedSalesChannelContextFactory extends AbstractSalesChannelContextFactory
{
    public function create(string $token, string $salesChannelId, array $options = []): SalesChannelContext
    {
        if (!$this->isCacheable($options)) {
            return $this->getDecorated()->create($token, $salesChannelId, $options);
        }

        // ...
    }

    private function isCacheable(array $options): bool
    {
        return !isset($options[SalesChannelContextService::CUSTOMER_ID])
            && !isset($options[SalesChannelContextService::BILLING_ADDRESS_ID])
            && !isset($options[SalesChannelContextService::SHIPPING_ADDRESS_ID]);
    }
}
```

However, since there is also data in the context that is independent of a customer's data, it is possible to cache some of this resource-costing data across customers, even if the customer is logged in, has selected a different payment method, shipping method or address. For this we have implemented the `Shopware\Core\System\SalesChannel\Context\BaseContextFactory`, which is responsible for creating the `Shopware\Core\System\SalesChannel\BaseContext`. Only data that belongs to the sales channel or is independent of the customer account is loaded into the `BaseContext`:
```php
<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

class BaseContext
{
    protected CustomerGroupEntity $currentCustomerGroup;

    protected CustomerGroupEntity $fallbackCustomerGroup;

    protected CurrencyEntity $currency;

    protected SalesChannelEntity $salesChannel;

    protected TaxCollection $taxRules;

    protected PaymentMethodEntity $paymentMethod;

    protected ShippingMethodEntity $shippingMethod;

    protected ShippingLocation $shippingLocation;

    protected Context $context;

    private CashRoundingConfig $itemRounding;

    private CashRoundingConfig $totalRounding;
}
```

The `BaseContextFactory` as well as the `BaseContext` are both marked as `@internal` and are not intended for extensions. Any intervention in the loading of the BaseContext can quickly lead to cache misses and is therefore not supported.

In addition to the corresponding `$salesChannelId`, the current session parameters are passed to the service, which contains a list of changed parameters. The `BaseContextFactory` takes into account the following parameters, which also have an effect on the corresponding cache permutation of the service:
* `shippingMethodId` - Contains the id of the selected shipping method.
* `paymentMethodId` - Contains the id of the selected payment method
* `countryId` - Contains the id of the selected shipping country
* `countryStateId` - Contains the id of the selected shipping state
* `currencyId` - Contains the id of the selected currency
* `languageId` - Contains the id of the selected language

In addition to the `Shopware\Core\System\SalesChannel\Context\BaseContextFactory` the `Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory` was implemented, which is responsible for caching the base context. It assembles the cache key based on the parameters listed above and loads the base context from the cache if it has already been loaded once.
```php
<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

class CachedBaseContextFactory extends AbstractBaseContextFactory
{
    public function create(string $salesChannelId, array $options = []): BaseContext
    {
        ksort($options);

        $keys = \array_intersect_key($options, [
            SalesChannelContextService::CURRENCY_ID => true,
            SalesChannelContextService::LANGUAGE_ID => true,
            SalesChannelContextService::DOMAIN_ID => true,
            SalesChannelContextService::PAYMENT_METHOD_ID => true,
            SalesChannelContextService::SHIPPING_METHOD_ID => true,
            SalesChannelContextService::VERSION_ID => true,
            SalesChannelContextService::COUNTRY_ID => true,
            SalesChannelContextService::COUNTRY_STATE_ID => true,
        ]);

        $key = implode('-', [$name, md5(json_encode($keys, \JSON_THROW_ON_ERROR))]);
        
        //...
    }
}
```

So now the caching of the Sales Channel context is handled on two levels:
* `CachedSalesChannelContextFactory`: Is responsible for global caching and provides a fast hit rate and load time for customers who are not logged in.
* `CachedBaseContextFactory`: Is responsible for caching generic objects that do not relate to the customer account. Once a customer has created the context for another payment or shipping method, it will be shared with all logged-in users.
