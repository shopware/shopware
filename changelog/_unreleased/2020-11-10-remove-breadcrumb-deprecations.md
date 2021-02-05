---
title: Remove breadcrumb deprecations
issue: NEXT-11661
author_github: @Dominik28111
---
# Core
* Removed method `buildSeoBreadcrumb()` in `Core\Content\Category\CategoryEntity`.
* Changed parameter `EntityRepositoryInterface` in `Core\Content\Category\Service\CategoryBreadcrumbBuilder` constructor. The parameter is required now.
___
# Storefront
* Removed parameter `navigationTree` and `categoryTree` in `Storefront/Resources/views/storefront/page/content/index.html.twig`.
* Removed parameter `navigationTree`, `categoryTree` and `product` in `Storefront/Resources/views/storefront/base.html.twig`.
