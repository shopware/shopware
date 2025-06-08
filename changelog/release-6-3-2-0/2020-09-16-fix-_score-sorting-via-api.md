---
title:              Fix _score sorting via API
issue:              NEXT-10713
author:             Hannes Wernery
author_email:       hannes.wernery@viison.com
author_github:      @hanneswernery
---
# Core
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryHelper` to add the sorting by _score only if a _score is actually added to the DBAL query

