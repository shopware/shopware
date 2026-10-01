---
title: Fix draco encoders path
issue: NEXT-39803
author: Simon Vorgers
author_email: s.vorgers@shopware.com
author_github: @Simon Vorgers
---
# Storefront
* Changed draco decoders directory from theme asset to storefront bundle asset
* Deprecated block `layout_head_javascript_assets_public_path` in  `src/Storefront/Resources/views/storefront/layout/meta.html.twig`
* Deprecated `window.themeAssetsPublicPath`. When js access to this path is needed, a window object could be set with `{{ asset('assets/', 'theme') }}` twig syntax, for example, extending a top block of the `src/Storefront/Resources/views/storefront/layout/meta.html.twig` template.