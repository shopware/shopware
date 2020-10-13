---
title: Hide listing pagination when having less than two pages
issue: NEXT-10650
---
# Storefront
* Added the condition `{% if totalPages > 1 %}` in block `component_pagination_nav` in `src/Storefront/Resources/views/storefront/component/pagination.html.twig` to hide the pagination navigation when having less than two pages.