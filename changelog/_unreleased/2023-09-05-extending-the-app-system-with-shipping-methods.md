---
title: extending the app system with shipping methods
issue: NEXT-30217
---
# Core

* Added new entity `app_shipping_method` in `Shopware\Core\Framework/App/Aggregate/AppShippingMethod/AppShippingMethodDefinition`
* Added association with `app_shipping_method` to `shipping_method`
* Added association with `app_shipping_method` to `app`
* Added association with `app_shipping_method` to `media`
* Added following new classes
    * `Shopware\Core\Framework\App\Lifecycle\Persister\ShippingMethodPersister`
    * `Shopware\Core\Framework\App\Manifest\Xml\ShippingMethods`
    * `Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod`
* Changed `Shopware\Core\Framework\App\Lifecycle\AppLifecycle` to reflect shipping method life cycle
* Changed `Shopware\Core\Framework\App\Manifest\Schema\manifest-2.0.xsd` to add new shipping methods by app manifest
