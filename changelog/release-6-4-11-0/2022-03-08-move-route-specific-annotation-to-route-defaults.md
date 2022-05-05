---
title: Move route specific annotation to route defaults
issue: NEXT-15014
---

# Core

* Changed all `@Captcha`, `@LoginRequired`, `@Acl`, `@ContextTokenRequired` and `@RouteScope` annotations to Route defaults to prevent issues while decorating controllers
* Deprecated the following annotations for 6.5.0.0 `@Captcha`, `@LoginRequired`, `@Acl`, `@ContextTokenRequired` and `@RouteScope`
___
# Upgrade Information

## Removal of deprecated route specific annotations

The following annotations has been removed `@Captcha`, `@LoginRequired`, `@Acl`, `@ContextTokenRequired` and `@RouteScope` and replaced with Route defaults. See below examples of the migration

### @Captcha

```php
/**
 * @Captcha
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"})
 */
```

to

```php
/**
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"}, defaults={"_captcha"=true})
 */
```

### @LoginRequired

```php
/**
 * @LoginRequired
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"})
 */
```

to

```php
/**
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"}, defaults={"_loginRequired"=true})
 */
```

### @Acl

```php
/**
 * @Acl({"my_plugin_do_something"})
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"})
 */
```

to

```php
/**
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"}, defaults={"_acl"={"my_plugin_do_something"}})
 */
```


### @ContextTokenRequired

```php
/**
 * @ContextTokenRequired
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"})
 */
```

to

```php
/**
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"}, defaults={"_contextTokenRequired"=true})
 */
```

### @RouteScope

```php
/**
 * @RouteScope(scopes={"api"})
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"})
 */
```

to

```php
/**
 * @Route("/account/register", name="frontend.account.register.save", methods={"POST"}, defaults={"_routeScope"={"api"}})
 */
```
