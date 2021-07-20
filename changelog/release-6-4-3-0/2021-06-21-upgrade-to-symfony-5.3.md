---
title: Upgrade to Symfony 5.3
issue: NEXT-15687
---
# Core
* Changed all `symfony/*` packages to 5.3
___
# Upgrade Information

## Storefront Controller need to have Twig injected in future versions

The `twig` service will be private with upcoming Symfony 6.0. To resolve this deprecation, a new method `setTwig` was added to the `StorefrontController`.
All controllers which extends from `StorefrontController` need to call this method in the dependency injection definition file (services.xml) to set the Twig instance.
The controllers will work like before until the Symfony 6.0 update will be done, but they will create a deprecation message on each usage.
Below is an example how to add a method call for the service using the XML definition.

### Before

```xml
<service id="Shopware\Storefront\Controller\AccountPaymentController">
    <call method="setContainer">
        <argument type="service" id="service_container"/>
    </call>
</service>
```

### After

```xml
<service id="Shopware\Storefront\Controller\AccountPaymentController">
    <call method="setContainer">
        <argument type="service" id="service_container"/>
    </call>
    <call method="setTwig">
        <argument type="service" id="twig"/>
    </call>
</service>
```
