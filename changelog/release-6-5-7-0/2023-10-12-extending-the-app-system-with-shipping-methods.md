---
title: Extending the app system with shipping methods
issue: NEXT-30229
---

# Core

+ Added the possibility to add new shipping methods via app manifest
* Added new entity `app_shipping_method` in `Shopware\Core\Framework/App/Aggregate/AppShippingMethod/AppShippingMethodDefinition`
* Added following new classes
    * `Shopware\Core\Framework\App\Lifecycle\Persister\ShippingMethodPersister`
    * `Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod\ShippingMethods`
    * `Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod\ShippingMethod`
    * `Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod\DeliveryTime`
