---
title: a11y insufficient accessibility of the search form field for screen readers
issue: 7093
author: Le Nguyen
author_email: nguyenquocdaile@gmail.com
author_github: @nguyenquocdaile
---
# Storefront
* Added `role="combobox"`, `aria-autocomplete="list"`, ` aria-controls="search-suggest-listbox"`, `aria-expanded="false"` attributes to `layout_header_search_input` block in `Storefront/Resources/views/storefront/layout/header/search.html.twig`.
* Added `role="listbox"` to `ul` tag element and `role="option"` to `li` tag element in `Storefront/Resources/views/storefront/layout/header/search-suggest.html.twig`.
