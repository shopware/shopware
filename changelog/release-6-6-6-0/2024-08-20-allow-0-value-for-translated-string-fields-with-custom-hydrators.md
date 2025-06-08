---
title: Allow "0" value for translated string fields with custom hydrators
issue: NEXT-37993
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed hydration of translated fields in `Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator::translate` to check if the value is strictly equal to null and not just empty, to allow the value "0" for translated fields
