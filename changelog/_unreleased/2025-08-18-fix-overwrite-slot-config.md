---
title: fix overwrite slot config
issue: 11800
---
# Core
* Replaced usage of `array_replace_recursive` with a custom `overrideArray` method in `SalesChannelCmsPageLoader.php`, ensuring that associative arrays are merged recursively and indexed arrays are fully replaced, giving more predictable override behavior.
* Added the `overrideArray` helper method to `SalesChannelCmsPageLoader.php` to support the new merging logic.