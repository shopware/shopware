---
title: Upgrade to Symfony 5.3
issue: NEXT-15687
---
# Core
* Changed all `symfony/*` packages to 5.3
___
# Upgrade Information

## Storefront Controller needs Twig injected

The `twig` service will be private in Symfony 6.0. To resolve this deprecation we added a new method `setTwig` to the `StorefrontController`.
All controllers which extends from `StorefrontController` needs to have a method call in the dependency injection to set the twig instance using the `setTwig` method.
See below an example how to add a method call for the service using xml definition.

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
