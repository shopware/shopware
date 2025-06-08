---
title: Add pseudo modal twig blocks
issue: NEXT-14691
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Storefront
* Added new blocks to `pseudo-modal.html.twig`:
    - `component_pseudo_modal`
    - `component_pseudo_modal_header`
    - `component_pseudo_modal_title`
    - `component_pseudo_modal_close_btn`
    - `component_pseudo_modal_close_btn_content`
    - `component_pseudo_modal_body`
    - `component_pseudo_modal_back_btn_content`
* Deprecated block `product_detail_zoom_modal_close_button_content` in `pseudo-modal.html.twig`. Use block `component_pseudo_modal_close_btn_content` instead.
