---
title: App script product pricing
date: 2023-02-01
area: core
tags: [app-script, product, pricing]
---

## Context
We want to provide the opportunity to manipulate the price of a product inside the cart and within the store.
For the cart manipulation we already have a hook integrated which allows accessing and manipulating the cart.
Right now we are not allowing to manipulate prices directly but just creating discounts or new price objects and add them as new line items into the cart.

However, there are different business cases which require a direct price manipulation like `get a sample of the product for free`

The following code can be used for manipulating the prices in the product-pricing hook:
```php

{% foreach hook.products as product %}
    {# allow resetting product prices #}
    {% do product.calculatedCheapestPrice.reset %}
    {% do product.calculatedPrices.reset %}
    {# not allowed to RESET the default price otherwise it is not more valid
    
    {# get control of the default price calculation #}
    {% set price = services.prices.create({
       'default': { 'gross': 20, 'net': 20 },
       'USD': { 'gross': 15, 'net': 15 },
       'EUR': { 'gross': 10, 'net': 10 }
    }) %}
    
    {# directly changes the price to a fix value #}
    {% do product.calculatedPrice.change(price) %}
    
    {# manipulate the price and subtract the provided price object #}
    {% do product.calculatedPrice.minus(price) %}
    
    {# manipulate the price and add the provided price object #}
    {% do product.calculatedPrice.plus(price) %}
    
    {# the following examples show how to deal with percentage manipulation #}
    {% do product.calculatedPrice.discount(10) %}
    {% do product.calculatedPrice.surcharge(10) %}
    
    {# get control of graduated prices #}
    {% do product.calculatedPrices.reset %}
    {% do product.calculatedPrices.change([
        { to: 20, price: services.prices.create({ 'default': { 'gross': 15, 'net': 15} }) },
        { to: 30, price: services.prices.create({ 'default': { 'gross': 10, 'net': 10} }) },
        { to: null, price: services.prices.create({ 'default': { 'gross': 5, 'net': 5} }) },
    ]) %}
    
    {# after hook => walk through prices and fix "from/to" values #}
    
    {% do product.calculatedCheapestPrice.change(price) %}
    {% do product.calculatedCheapestPrice.minus(price) %}
    {% do product.calculatedCheapestPrice.plus(price) %}
    {% do product.calculatedCheapestPrice.discount(10) %}
    {% do product.calculatedCheapestPrice.surcharge(10) %}

{% endforeach %}
```

The following code can be used to manipulate the prices of a product inside the cart:

```php
{# manipulate price of a product inside the cart #}
{% set product = services.cart.get('my-product-id') %}

{% set price = services.prices.create({
   'default': { 'gross': 20, 'net': 20 }
}) %}

{% do product.price.change(price) %}

{% do product.price.discount(10) %}
{% do product.price.surcharge(10) %}
```
