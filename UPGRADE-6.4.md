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

Storefront
--------------

Refactorings
------------
