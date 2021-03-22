---
title: Add prefix and suffix filter to the DAL
issue: NEXT-13886
author: Felix Brucker
author_email: felix@felixbrucker.com
author_github: felixbrucker
---
# Core
* Added new classes `Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter` and `Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter`.
___
# Administration
* Added static `prefix()` and `suffix()` filter methods to the `Criteria` class (`src/Administration/Resources/app/administration/src/core/data/criteria.data.js`).
