---
title: Refactor providing of cookies
issue: 9451
author: Björn Meyer, Michael Telgmann
author_github: BrocksiNet, mitelg
---

# Core

* Added `\Shopware\Core\Content\Cookie\SalesChannel\CookieRoute` as new service to retrieve all registered cookie groups and their cookie entries.
* Added `\Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent` as new extension point to provide additional cookie groups and/or cookie entries.

___

# Storefront

* Deprecated `\Shopware\Storefront\Framework\Cookie\CookieProviderInterface`. Use `\Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent` instead.
* Deprecated `\Shopware\Storefront\Framework\Cookie\CookieProvider`
* Deprecated `\Shopware\Storefront\Framework\Cookie\AppCookieProvider`
* Deprecated usage of `snippet_name` on cookies in Twig templates. Use `snippetKeyName` instead.
* Deprecated usage of `snippet_description` on cookies in Twig templates. Use `snippetKeyDescription` instead.

___

# API

* Added new Store-API endpoint `/store-api/cookie-groups` to retrieve all registered cookie groups and their cookie entries.

___

# Upgrade Information

## Refactor of providing cookies

The providing of cookies has been refactored.
With this the new route `/store-api/cookie-groups` has been added to retrieve all registered cookie groups and their cookie entries.
This route is provided by the new `\Shopware\Core\Content\Cookie\SalesChannel\CookieRoute` service.
The `\Shopware\Storefront\Framework\Cookie\CookieProviderInterface` has been deprecated and so all its implementations.
They will be removed in the next major version.
To register new cookie groups and cookie entries, the new `\Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent` should be used instead.
Additionally, the `snippet_name` and `snippet_description` properties on cookies in Twig templates have been deprecated.
Use `snippetKeyName` and `snippetKeyDescription` instead.

___

# Next Major Version Changes

## Refactor of providing cookies

The `\Shopware\Storefront\Framework\Cookie\CookieProviderInterface` and all its implementations were removed.
Use the `\Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent` instead to register new cookie groups and cookie entries.
The `snippet_name` and `snippet_description` properties on cookies in Twig templates have been removed.
Use `snippetKeyName` and `snippetKeyDescription` instead.
