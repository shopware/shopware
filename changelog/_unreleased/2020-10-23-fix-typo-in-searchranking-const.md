---
title: Fix typo in SearchRanking const
issue: NEXT-11637
author: Rune Laenen
author_email: rune@laenen.nu 
author_github: @runelaenen
---
# Core
*  Deprecated const `\Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking::LOW_SEARCH_RAKING`
*  Added const `\Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking::LOW_SEARCH_RANKING`
*  Changed current usage of const `LOW_SEARCH_RAKING` to `LOW_SEARCH_RANKING`
