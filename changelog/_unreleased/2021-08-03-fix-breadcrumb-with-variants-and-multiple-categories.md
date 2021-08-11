---
title: Fix breadcrumb with variants and multiple categories
issue: NEXT-15069
author_github: @Dominik28111
---
# Core
* Changed method `Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder::getMainCategory()` to lookup main categories of parent product.
