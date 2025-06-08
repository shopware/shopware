---
title: Allow `ce_` shorthand prefix for custom entities
issue: NEXT-21532
---
# Core
* Changed Custom Entity registration and API to allow the use of the `ce_` shorthand prefix for custom entities.
* Deprecated the `\Shopware\Core\System\CustomEntity\Api\CustomEntityApiController` class, it will ne internal from v6.5.0.0, only rely on the HTTP-API it provides.
___
# Upgrade information
## `ce_` shorthand prefix for custom entities
You can now prefix your custom entities with the `ce_` shorthand prefix, to prevent running into DB limits on the length of table and columns names.
