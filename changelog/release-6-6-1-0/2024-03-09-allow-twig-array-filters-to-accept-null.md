---
title: Allow Twig array filters to accept null
issue: NEXT-34410
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed the `Shopware\Core\Framework\Adapter\Twig\SecurityExtension` to accept `null` for the Twig filters `map`, `reduce`, `filter` and `sort`
