---
title: Move script tags to head with defer
issue: NEXT-23945
---
# Storefront
* Removed deprecated blocks in `Resources/views/storefront/base.html.twig` due to them being moved to the `<head>` with defer. Use blocks inside `Resources/views/storefront/layout/meta.html.twig` instead.
    * Removed deprecated block `base_script_token`, use `layout_head_javascript_token` instead.
    * Removed deprecated block `base_script_router`, use `layout_head_javascript_router` instead.
    * Removed deprecated block `base_script_breakpoints`, use `layout_head_javascript_breakpoints` instead.
    * Removed deprecated block `base_script_wishlist_state`, use `layout_head_javascript_wishlist_state` instead.
    * Removed deprecated block `base_script_hmr_mode`, use `layout_head_javascript_hmr_mode` instead.
