---
title: Use correct languages to update product search keywords.
issue: NEXT-30332
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed method `update` in `Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater` class to use the association betwenn language and sales channels to determine if a language is in use.
