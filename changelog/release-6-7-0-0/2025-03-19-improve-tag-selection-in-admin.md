---
title: Improve tag selection in the administration
issue: #7544
---
# Administration
* Changed `component/form/select/entity/sw-entity-multi-select/sw-entity-multi-select.html.twig` to pass down the `disabled` property to the `sw-select-base` component to properly implement the disabled state.
* Changed `module/sw-order/component/sw-order-general-info/sw-order-general-info.html.twig` to properly use the tag collection on `sw-entity-tag-select` with `v-model`.
