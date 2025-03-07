---
title: Make off-canvas navigation accessible
issue: NEXT-40780
---
# Storefront
* Changed the `offcanvas-menu.plugin.js` to fix several issues and make it accessible via keyboard:
  * Removed the animation. The sub-category overlay will now simply be shown, which fixes additional issues like unnecessary scrollbars.
  * Removed some unnecessary wrapper elements to simply the DOM structure.
  * Removed a lot of unnecessary methods according to the simplified behaviour.
  * Changed the overlay behaviour so the focus will be correctly set to the overlay and back when navigating between parent and sub-categories.
* Changed the close button of off-canvas elements to `btn-secondary` and added styling for `:focus-visible`.
* Removed unnecessary wrapper element in `storefront/layout/navigation/offcanvas/categories.html.twig`.
* Changed the CSS class naming of `.navigation-offcanvas-container` in `storefront/layout/navigation/offcanvas/navigation.html.twig` to simplify the structure.
* Added `:focus-visible` styling to `.navigation-offcanvas-link` to support keyboard navigation.
* Added some margin and padding to `.navigation-offcanvas-actions` to give the layout a bit more space.
