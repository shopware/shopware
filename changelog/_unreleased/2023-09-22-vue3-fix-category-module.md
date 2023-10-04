---
title: Vue 3 Fix category module
issue: NEXT-28992
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Changed `this.$slots.default[0].text` in `sw-product-variant-info` to `this.$slots?.default?.()?.[0]?.children`
* Changed `sw-product-stream-grid-preview.html.twig` to set auto-height to `true` for the empty state
* Changed `sw-landing-page-tree/index.js` to avoid using `Vue.$set` for `loadedLandingPages`
* Changed `sw-landing-page-detail-base.html.twig` to use the named `v-model` in Vue 3 for the `sw-entity-tag-select`
