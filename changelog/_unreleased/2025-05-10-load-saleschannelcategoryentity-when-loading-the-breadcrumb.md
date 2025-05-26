---
title: Load `SalesChannelCategoryEntity` when loading the breadcrumb
author: Max
author_github: @aragon999
---
# Core
* Changed the Twig functions `sw_breadcrumb_full()` and `sw_breadcrumb_full_by_id()` to load `SalesChannelCategoryEntity` in order to properly display the corresponding seo link in the breadcrumb
___
# Upgrade Information
change in the Twig templates
```
sw_breadcrumb_full(category, context.context)
sw_breadcrumb_full_by_id(category, context.context)
```
to
```
sw_breadcrumb_full(category, context)
sw_breadcrumb_full_by_id(category, context)
```
