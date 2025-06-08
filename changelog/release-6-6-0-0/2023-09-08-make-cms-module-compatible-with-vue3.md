---
title: Make CMS module compatible with Vue3
issue: NEXT-28998
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Changed data-mapping icon in sw-text-editor from old icon which is not available anymore to the new `regular-variables-xs` icon
* Changed v-model inside the `sw-cms-slot` component to use `v-model:element` at the dynamic `<component :is="elementConfig.commponent" />` component
* Changed v-model inside the `sw-cms-el-config-image` component to use `v-model:config` at the `sw-cms-mapping-field` component
* Changed the v-for in the `sw-cms-el-image-gallery` for the `sw-media-list-selection-item-v2` to a wrapper `template` component
* Changed v-model inside the `sw-cms-el-config-image-gallery` component to use `v-model:config` at the `sw-cms-mapping-field` component
* Changed v-model inside the `sw-cms-el-config-text` component to use `v-model:config` at the `sw-cms-mapping-field` component
* Changed v-model inside the `sw-cms-el-config-vimeo-video` component to use `v-model:config` at the `sw-cms-mapping-field` component
* Changed v-model inside the `sw-cms-el-config-youtube-video` component to use `v-model:config` at the `sw-cms-mapping-field` component
* Changed v-model inside the `sw-cms-slot` component to use `v-model:element` at the dynamic `<component :is="elementConfig.commponent" />` component
* Changed the render methos of `sw-media-quickinfo-metadata-item` to support Vue3
