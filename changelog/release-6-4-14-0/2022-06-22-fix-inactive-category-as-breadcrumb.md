---
title: Fix inactive category as breadcrumb
issue: NEXT-21371
author: Daniel Beyer
author_email: d.beyer@shopware.com
---
# Core
* Added a filter to `\Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder::getProductSeoCategory` so that inactive categories are not considered anymore.
