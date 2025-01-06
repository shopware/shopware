---
title: Improved category and product indexing for many entities at once
issue: NEXT-38846
author: Michael Telgmann
author_github: @mitelg
---
# Core
* Added delayed indexing for to-be-updated categories and their children in `\Shopware\Core\Content\Category\DataAbstractionLayer\CategoryIndexer` if a certain threshold is reached.
* Added delayed indexing for to-be-updated products in `\Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer` if a certain threshold is reached.
