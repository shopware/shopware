---
title: Move controller level annotation into Symfony route annotation
date: 2022-02-09
area: core
tags: [annotations, controller, route, defaults]
---

## Context

Annotations are used to configure controllers in the core currently. 
The configuration can contain the following as example:

- @LoginRequired
    - Customer needs to be logged in
- @Acl
    - Protects the controller with special acl privileges
- @RouteScope
    - Defines the scope of the route
- and many more

As Annotations are bound to the implementing class, all decorators have to copy and be updated to date with the target class

## Decision

We replace the Annotations from all controllers and define them in the `defaults` of the Symfony `@Route` annotation. The custom annotation will be deprecated for removal in 6.5.0.

Here is an example of the `@LoginRequired` migration:

### Before

```php
@LoginRequired
@Route("/store-api/product", name="store-api.product.search", methods={"GET", "POST"})
public function myAction()
```

### After

```php
@Route("/store-api/product", name="store-api.product.search", methods={"GET", "POST"}, defaults={"_loginRequired"=true})
public function myAction()
```

Symfony passes the defaults to the attribute bag of the Request object, and we can check the attributes in the request cycle of the http kernel.

The following annotations will be replaced:
- `@Captcha` -> `_captcha`
- `@LoginRequired` -> `_loginRequired`
- `@Acl` -> `_acl`
- `@ContextTokenRequired` -> `_contextTokenRequired`
- `@RouteScope` -> `_routeScope`


Extensions can still decorate the controller if it has an abstract class or use events like `KernelEvents::REQUEST` or `KernelEvents::RESPONSE` to execute code before or after the actual controller.
