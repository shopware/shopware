---
title: Load snippets from every inherited theme
issue: 6331
author: Dominik Grothaus
author_email: d.grothaus@shopware.com
---
# Storefront
* Changed `Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader` to resolve the used themes of a sales channel from both `theme.parent_theme_id` and the `configInheritance` list stored in `theme.base_config`, walking the ancestors transitively. A theme naming several ancestors in its `theme.json` previously only received the snippets of the ancestors that happened to lie on the single `parent_theme_id` chain, so the snippets of the others were treated as unused and filtered out.
