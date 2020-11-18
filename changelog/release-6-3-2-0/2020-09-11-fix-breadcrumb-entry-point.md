---
title: Fix breadcrumb entry point
issue: NEXT-8568
author_github: @Dominik28111
---
# Core
* Fixed a bug that breadcrumbs displayed entry point categories of a sales channel
* Deprecated `Shopware\Core\Content\Category\CategoryEntity::buildSeoBreadcrumb()` use `Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder` instead
* Added twig function `sw_breadcrumb` to build category breadcrumb array `{% set breadcrumb = sw_breadcrumb(category) %}`
