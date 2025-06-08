---
title: Handle bool setup as string in CustomerCustomFieldRule
issue: NEXT-22823
author: Leszek Prabucki
author_email: leszek.prabucki@gmail.com
author_github: l3l0
---
# Core
* Changed `Shopware\Core\Checkout\Customer\Rule\CustomerCustomFieldRule` to allow values for bool type custom fields be evaluated as string (`'true'` or `'false'`).
