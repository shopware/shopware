---
title: Remove abandoned `sensio/framework-extra-bundle` dependency
issue: NEXT-24873
---
# Core
* Removed the abandoned `sensio/framework-extra-bundle` dependency
* Deprecated `@HttpCache`, `@Entity` and `@NoStore` annotations for routes, the configurations now need to be configured as `defaults` in the `@Route` annotation
___
# Upgrade Information
## Changed `HttpCache`, `Entity` and `NoStore` configurations for routes

The Route-level configurations for `HttpCache`, `Entity` and `NoStore` where changed from custom annotations to `@Route` defaults.
The reasons for those changes are outlined in this [ADR](../../adr/api/2022-02-09-controller-configuration-route-defaults.md) and for a lot of former annotations this change was already done previously.
Now we also change the handling for the last three annotations to be consistent and to allow the removal of the abandoned `sensio/framework-extra-bundle`.

This means the `@HttpCache`, `@Entity`, `@NoStore` annotations are deprecated and have no effect anymore, the configuration no needs to be done as `defaults` in the `@Route` annotation.

Before:
```php
/**
 * @Route("/my-route", name="my.route", methods={"GET"})
 * @NoStore
 * @HttpCache(maxage="3600", states={"cart.filled"})
 * @Entity("product")
 */
public function myRoute(): Response
{
    // ...
}
```

After:
```php
/**
 * @Route("/my-route", name="my.route", methods={"GET"}, defaults={"_noStore"=true, "_httpCache"={"maxage"="3600", "states"={"cart.filled"}}, "_entity"="product"})
 */
public function myRoute(): Response
{
    // ...
}
```
