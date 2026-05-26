---
title: Allow extending EntitySearchResult
author: Umut Dogan
author_email: u.dogan@shopware.com
author_github: @umutdogan
---
# Core
* Removed the `@final` annotation from `Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult` and updated `createNew()` to preserve the concrete entity collection type for extending classes.
