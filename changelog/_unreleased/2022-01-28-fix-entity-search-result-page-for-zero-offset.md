---
title: Fix EntitySearchResult page for zero offset
issue: NEXT-19868
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult::__construct` to calculate the correct page if offset is zero
