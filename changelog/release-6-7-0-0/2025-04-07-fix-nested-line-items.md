---
title: Fix nested line items
issue: #8321
---
# Storefront
* Changed element `line-item-children-elements` from `<div>` to `<ul>` in `Resources/views/storefront/component/line-item/element/children-wrapper.html.twig` to fix nested line-items not being nested into the collapse panel.