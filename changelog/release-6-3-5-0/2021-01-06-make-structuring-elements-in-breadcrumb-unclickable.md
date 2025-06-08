---
title: Make structuring elements in breadcrumb unclickable
issue: NEXT-12897
author_github: @Dominik28111
---
# Core
* Added twig function `sw_breadcrumb_types`
___
# Storefront
* Added new variable `breadcrumbTypes` in `component/listing/breadcrumb.html.twig`
* Changed `{% block component_listing_breadcrumb_category %}` in `component/listing/breadcrumb.html.twig`
* Added new variable `breadcrumbTypes` in `component/product/breadcrumb.html.twig`
* Changed `{% block component_product_breadcrumb_category %}` in `component/product/breadcrumb.html.twig`
