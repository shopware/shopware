---
title: Accessibility of keyboard navigation on mobile
issue: NEXT-40778
---
# Storefront
* Changed `search-widget.plugin.js` to not add a `tabindex="-1"` to the search input on mobile, so it can be reached via keyboard.
* Changed the `offcanvas.plugin.js` to always use the `click` event for close buttons, even on mobile, so keyboard navigation is possible.
