---
title: Fix the number slops to find numbers between non-digits
issue: NEXT-40382
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Core
* Changed `\Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter::slop` to also find the number between non-digits.
