---
title: Load snippets from every inherited theme
issue: 6331
author: Dominik Grothaus
author_email: d.grothaus@shopware.com
---
# Storefront
* Changed `Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader` to resolve the used themes of a sales channel from both `theme.parent_theme_id` and the `configInheritance` list in `theme.base_config`. A theme listing several ancestors in its `theme.json` now receives the snippets of all of them.
