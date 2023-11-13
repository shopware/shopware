---
title: Introducing tax providers
date: 2022-04-28
area: checkout
tags: [tax, tax-provider, checkout]
--- 

## Context
In other countries like the USA, there are different tax rates for different states and counties you are shipping to, leading to thousands of different tax rates in the USA alone.
For this purpose, *tax providers* exist like `TaxJar`, `Vertex` or `AvaTax` that output the tax rate depending on the customer and cart details.

## Decision
We want to implement a possibility (interface / hook), which is called after the cart is calculated and is able to overwrite the taxes.
Then, when a customer is logged in (therefore information about the shipping / billing is available), we can call the interface to receive all necessary information about the tax rates.

## Implementation details

### New entity `tax_provider`

We want to create a new entity called `tax_provider` which registers the available tax providers and defines rules.

The following fields should therefore be required:

* IdField `id`
* TranslatedField `name`
* IntField `priority` (default 1)
* FkField `availabilityRuleId`
* StringField `providerIdentifier` (unique)
* TranslatedField `customFields`

### Location and prioritization of *tax providers*

The `TaxProviderProcessor` is called in the `CartRuleLoader`, after the whole cart has been calculated (so all the promotions and deliveries are calculated).
Therefore, if any rules may change due to the changed taxes (e.g. gross price), they will not be validated anymore.

The *tax provider* will only be called, if:
* A customer is logged in
* The availability rule matches

The highest priority defines, which *tax provider* is called first. If no parameter is filled or the `TaxProviderNotAvailableException` is thrown, the next *tax provider* by priority is called.

### Calling the *tax provider*

The `TaxProviderProcessor` will call a class that is tagged `shopware.tax.provider`, named in the `providerIdentifier` and implements the `TaxProviderInterface`.
If the class does not exist, the Processor will throw a `TaxProviderHook`, that has the identifier and the return struct as additional parameters, so it can be filled via app scripting, if the identifier matches with the app.
To allow for app scripting to call the provider, we need to add a possibility to do requests to the app, e.g. via Guzzle.

```php
interface TaxProviderInterface
{
    /**
     * @throws TaxProviderOutOfScopeException|\Throwable
     */
    public function provideTax(Cart $cart, SalesChannelContext $context): TaxProviderStruct;
}
```

If a *tax provider* throws any other Exception than the `TaxProviderOutOfScopeException` (e.g. due to connection issues), we proceed to the next tax provider.
If no other provider can provide taxes, we will throw the first Exception since we then don't want any invalid taxes.

### Return & Processing

If any of the values of the TaxProviderStruct is filled by the class / hook, we do not call any more TaxProviders.
Afterwards, the line items / shipping costs / total tax are respectively overwritten, before the cart is persisted.

```php
class TaxProviderStruct extends Struct 
{
    /**
     * @param null|array<string, CalculatedTaxCollection> key is line item id
     */
    protected ?array $lineItemTaxes = null;

    /**
     * @param null|array<string, CalculatedTaxCollection> key is delivery id
     */
    protected ?array $deliveryTaxes = null;

    protected ?CalculatedTaxCollection $cartPriceTaxes = null;
}
```
