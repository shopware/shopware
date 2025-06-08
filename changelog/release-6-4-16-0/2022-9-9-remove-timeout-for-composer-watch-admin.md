---
title: Remove timeout for composer watch:admin
issue: NEXT-23097
flag:
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Core
* Changed `composer.json` to disable the `process-timeout` for the following commands:
    * `watch:admin`
    * `watch:storefront`
    * `e2e:open`
    * `e2e:admin:open`
    * `e2e:storefront:open`
    * `e2e:recovery:open`
    * `admin:unit:watch`
    * `storefront:unit:watch`