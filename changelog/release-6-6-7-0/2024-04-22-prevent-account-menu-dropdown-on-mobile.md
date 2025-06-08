---
title: Prevent account menu dropdown on mobile
issue: NEXT-35455
---
# Storefront
* Deprecated `options.hiddenClass` in `OffCanvasAccountMenu`. Will be removed because the dropdown does not open in the first place when `_isInAllowedViewports()` is `true`.
