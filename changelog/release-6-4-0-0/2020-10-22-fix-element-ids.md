---
title: Fix data cms element ids in storefront
issue: NEXT-11767
author: Sebastian König
author_email: s.koenig@tinect.de
author_github: @tinect
---
# Storefront
* Changed several cms-blocks to add `element.id` correctly and
* Deprecated variable `id` in the same blocks:
    * `{% block block_center_text %}` in `src/Storefront/Resources/views/storefront/block/cms-block-center-text.html.twig`
    * `{% block block_image_bubble_row %}` in `src/Storefront/Resources/views/storefront/block/cms-block-image-bubble-row.html.twig`
    * `{% block block_image_four_column %}` in `src/Storefront/Resources/views/storefront/block/cms-block-image-four-column.html.twig`
    * `{% block block_image_highlight_row %}` in `src/Storefront/Resources/views/storefront/block/cms-block-image-highlight-row.html.twig`
    * `{% block block_image_simple_grid %}` in `src/Storefront/Resources/views/storefront/block/cms-block-image-simple-grid.html.twig`
    * `{% block block_image_text_bubble %}` in `src/Storefront/Resources/views/storefront/block/cms-block-image-text-bubble.html.twig`
    * `{% block block_image_text_cover %}` in `src/Storefront/Resources/views/storefront/block/cms-block-image-text-cover.html.twig`
    * `{% block block_image_text_gallery %}` in `src/Storefront/Resources/views/storefront/block/cms-block-image-text-gallery.html.twig`
    * `{% block block_image_text_row %}` in `src/Storefront/Resources/views/storefront/block/cms-block-image-text-row.html.twig`
    * `{% block block_image_text %}` in `src/Storefront/Resources/views/storefront/block/cms-block-image-text.html.twig`
    * `{% block block_image_three_column %}` in `src/Storefront/Resources/views/storefront/block/cms-block-image-three-column.html.twig`
    * `{% block block_image_three_cover %}` in `src/Storefront/Resources/views/storefront/block/cms-block-image-three-cover.html.twig`
    * `{% block block_image_two_column %}` in `src/Storefront/Resources/views/storefront/block/cms-block-image-two-column.html.twig`
    * `{% block block_product_three_column %}` in `src/Storefront/Resources/views/storefront/block/cms-block-product-three-column.html.twig`
    * `{% block block_text_teaser_section %}` in `src/Storefront/Resources/views/storefront/block/cms-block-text-teaser-section.html.twig`
    * `{% block block_text_three_column %}` in `src/Storefront/Resources/views/storefront/block/cms-block-text-three-column.html.twig`
    * `{% block block_text_two_column %}` in `src/Storefront/Resources/views/storefront/block/cms-block-text-two-column.html.twig`
    
