---
title: Load `SalesChannelCategoryEntity` when loading the breadcrumb
author: Max
author_github: @aragon999
---
# Core
* Changed the Twig functions `sw_breadcrumb_full()` and `sw_breadcrumb_full_by_id()` to load `SalesChannelCategoryEntity` in order to properly display the corresponding seo link in the breadcrumb
___
# Upgrade Information
## Breadcrumb template functions require the `SalesChannelContext`

The Twig breadcrumb functions `sw_breadcrumb_full` and `sw_breadcrumb_full_by_id` now require the `SalesChannelContext`, i.e. adjust the default Twig templates as follows

```diff
- sw_breadcrumb_full(category, context.context)
- sw_breadcrumb_full_by_id(category, context.context)
+ sw_breadcrumb_full(category, context)
+ sw_breadcrumb_full_by_id(category, context)
```
