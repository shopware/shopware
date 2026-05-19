---
title: Improve accessibility of footer collapse sections on mobile.
issue: 40777
---
# Storefront
* Changed the collapse indicators in `footer.html.twig` from `<div>` to `<button>` to function as the single toggle for opening and closing the collapse panel. Moved the corresponding attributes from the parent `<div>` element to the toggle.
* Added a condition to collapse panel toggle elements for category navigation panels, to not show a toggle if there are no child categories, because the collapse panel would be empty.
* Changed `collapse-footer-columns.plugin.js` to properly handle the initialization of collapse panels between viewport changes and deal with the changed toggle element.
