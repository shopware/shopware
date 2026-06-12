---
title: Fix buttons overflowing viewport at 200% text zoom (WCAG SC 1.4.4)
author_github: @patzick
---
# Storefront
* Changed `$btn-white-space` from `nowrap` to `normal` in `src/Storefront/Resources/app/storefront/src/scss/skin/shopware/abstract/variables/_bootstrap.scss` so button text wraps instead of overflowing the viewport under constrained space (e.g. 320 px width + 200% text zoom).
* Removed `overflow: hidden` and `text-overflow: ellipsis` from the `.btn` base class in `src/Storefront/Resources/app/storefront/src/scss/skin/shopware/component/_button.scss` as they are no longer needed when text is allowed to wrap.
