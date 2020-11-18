UPGRADE FROM 6.3.x.x to 6.4
=======================

Table of contents
----------------

* [Core](#core)
* [Administration](#administration)
* [Storefront](#storefront)
* [Refactorings](#refactorings)

Core
----

* Implementations of `\Shopware\Core\Framework\Api\Sync\SyncServiceInterface::sync` need to change the type of the first argument `$operations` to `iterable`.

Administration
--------------
* `StateDeprecated` needs to be replaced with `State`
* `DataDeprecated`  needs to be replaced with `Data` (https://docs.shopware.com/en/shopware-platform-dev-en/developer-guide/administration/fetching-and-handling-data?category=shopware-platform-dev-en/developer-guide/administration)
* Rename folder in `platform/src/Administration/Resources/app/administration/src/core` from `data-new`
to `data`. You need to rewrite the imports.
* Removed deprecated data handling and all its usages. See in the changelog if you
extend or override them. If yes, then you need to rewrite your code to support the 
actual data handling.

Storefront
--------------

Refactorings
------------
