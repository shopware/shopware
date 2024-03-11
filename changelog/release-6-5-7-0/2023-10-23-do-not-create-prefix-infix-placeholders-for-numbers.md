---
title: Expect numeric search terms to be more precise apply less typo correction
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
issue: NEXT-31261
---
# Core
* Changed `\Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter::slop` to skip typo correction slop generation for numeric search tokens
