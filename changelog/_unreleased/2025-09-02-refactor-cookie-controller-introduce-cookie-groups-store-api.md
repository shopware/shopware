---
title: Refactor providing of cookies
issue: 9451
author: Björn Meyer, Michael Telgmann
author_github: BrocksiNet, mitelg
---

# Core

* Added `\Shopware\Core\Content\Cookie\SalesChannel\CookieRoute` as a new service to retrieve all registered cookie groups and their entries. This experimental Store API endpoint is already used by the Twig storefront (backward compatibility will be maintained), but future changes may be introduced for custom/composable frontends.
* Added `\Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent` as new extension point to provide additional cookie groups and/or cookie entries.

___

# Storefront

* Deprecated `\Shopware\Storefront\Framework\Cookie\CookieProviderInterface`. Use `\Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent` instead.
* Deprecated `\Shopware\Storefront\Framework\Cookie\CookieProvider`
* Deprecated `\Shopware\Storefront\Framework\Cookie\AppCookieProvider`
* Deprecated usage of `snippet_name` on cookies in Twig templates. Use `name` instead.
* Deprecated usage of `snippet_description` on cookies in Twig templates. Use `description` instead.

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
The way apps are registering cookies has not changed.

Additionally, the `snippet_name` and `snippet_description` properties on cookies in Twig templates have been deprecated.
Use `name` and `description` instead; these properties now serve both as snippet keys (before translation) and as translated strings (after translation), depending on the context in which they are accessed.

Adding new cookies before:
```php
class CustomCookieProvider implements CookieProviderInterface
{
    public function __construct(
        private readonly CookieProviderInterface $inner,
    ) {
    }

    public function getCookieGroups(): array
    {
        $cookieGroups = $this->inner->getCookieGroups();
        
        $cookieGroups[] = [
            'snippet_name' => 'cookie.group.name',
            'entries' => [
                [
                    'cookie' => 'cookie-name',
                ],
            ],
        ];
        
        return $cookieGroups;
    }
}
```

Adding new cookies now:
```php
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;

class AppCookieCollectListener
{
    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $cookieGroups = $event->cookieGroupCollection;
        $newCookieGroup = new CookieGroup('cookie.group.name');
        
        $newCookieEntry = new CookieEntry('cookie-name')
        $newCookieGroup->setEntries([$entry]);
        
        $cookieGroups->add($newCookieGroup);
    }
}
```

___

# Next Major Version Changes

## Refactor of providing cookies

The `\Shopware\Storefront\Framework\Cookie\CookieProviderInterface` and all its implementations were removed.
Use the `\Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent` instead to register new cookie groups and cookie entries.
The `snippet_name` and `snippet_description` properties on cookies in Twig templates have been removed.
Use `name` and `description` instead.
