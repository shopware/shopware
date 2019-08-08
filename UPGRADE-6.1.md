UPGRADE FROM 6.0 to 6.1
=======================

Core
----

*No changes yet*

Administration
--------------

*No changes yet*

Storefront
----------

* If your javascript lives in `Resources/storefront/script` you have to explicitly define this path in the `getStorefrontScriptPath()` method of your plugin base class as we have changed the default path to `Resources/dist/storefront/js`.

Elasticsearch
-------------

*No changes yet*
