---
title: Fix empty operator of customer zip code rule conditions
issue: NEXT-20173
author: d.neustadt
author_email: d.neustadt@shopware.com
author_github: dneustadt
---
# Core
* Changed `ZipCodeRule` to evaluate if operator is `ZipCodeRule::OPERATOR_EMPTY` and zip code is `null` before throwing exception
