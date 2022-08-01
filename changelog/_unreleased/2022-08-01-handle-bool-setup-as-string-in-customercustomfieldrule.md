---
title: Handle bool setup as string in CustomerCustomFieldRule
issue: 2622
author: Leszek Prabucki
author_email: leszek.prabucki@gmail.com
author_github: l3l0
---
# Core
* Allow setup custom field rule bool values as string (`'true'` or `'false'`). Changes in `getExpectedValue` of class `Shopware\Core\Checkout\Customer\Rule\CustomerCustomFieldRule`
