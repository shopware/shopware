---
title: Allow empty alt with sw_thumbnails
issue: #8228
---
# Storefront
* Changed `sw_thumbnails` function to accept empty alt attributes. `attributes: { alt: '' }` will render an empty `alt=""` in the HTML to mark images as decorative.