---
title: Improve accessibility of image zoom modal
issue: NEXT-33693
---
# Storefront
* Added `tabindex` to images within `cms-element-image-gallery.html.twig` to be accessible via keyboard navigation.
* Added `keydown` event listener to `zoom-modal.plugin.js` to enable opening the zoom modal via keyboard.
* Added focus state handling to `zoom-modal.plugin.js` to focus active image and return focus to previous element when closing.
