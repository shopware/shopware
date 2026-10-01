---
title: Improve test generator `generateSalesChannelContext`
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopware\Core\Test\Generator` by adding the `generateSalesChannelContext` function, which allows the generation of a complete dynamic sales channel context object
* Changed `Shopware\Core\Test\TestDefaults` by adding several TestDefault constants
___
# Next Major Version Changes
## Removal of Generator::createSalesChannelContext()
* The current static `Generator::createSalesChannelContext()` function lacks support for some SalesChannelContext components (e.g. ShippingLocation)
* To allow a complete dynamic and simple creation of the SalesChannelContext with the Generator we added the new `Generator::generateSalesChannelContext()` function, which supports all SalesChannelContext components out of the box and additionally has a `overrides` parameter to further directly customize the SalesChannelContext fields

change
```php 
Generator::createSalesChannelContext()
```
to
```php
Generator::generateSalesChannelContext()
```