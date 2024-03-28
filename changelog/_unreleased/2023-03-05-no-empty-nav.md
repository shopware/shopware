---
title: No empty nav tag
issue: NEXT-33684
---
# Storefront
* Changed `Storefront/Resources/views/storefront/layout/header/top-bar.html.twig` to ensure that the `<nav>` tag is hidden when content is not present
* Added block `layout_header_top_bar_inner` to `Storefront/Resources/views/storefront/layout/header/top-bar.html.twig`
___
# Upgrade Information
## Accessibility: No empty nav element in top-bar
There will be no empty `<nav>` tag anymore on single language and single currency shops so accessibility tools will not be confused by it.

On shops with only one language and one currency the blocks `layout_header_top_bar_language` or `layout_header_top_bar_currency` will not be rendered anymore.

If you still need to add content to the `<div class="top-bar d-none d-lg-block">` you should extend the new block `layout_header_top_bar_inner`.

If you add `<nav>` tags always ensure they are only rendered if they contain navigation links.
