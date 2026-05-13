---
title: Fix missing XXL breakpoint value in thumbnail sizes attribute
issue: 16710
---
# Storefront
* Changed `src/Storefront/Resources/views/storefront/utilities/thumbnail.html.twig` to emit a value for the XXL breakpoint in the auto-generated `sizes` attribute.
* The `xxl` key is now used for the largest (open-ended) viewport sizing branch (boxed: `container / columns` in `px`, full-width: `100 / columns` in `vw`). The `xl` key is now treated as a closed range bounded by `breakpoint.xxl - 1` to match the pattern used by the smaller breakpoints.
