---
title: Replace Viewport Helper html pseudo element to improve screen reader accessibility
issue: NEXT-26717
---
# Storefront
* Deprecated the `html:before` pseudo-element containing the current breakpoint as `content`. CSS variable `--sw-current-breakpoint` is used instead by `viewport-detection.helper.js` to no longer confuse the screen reader.