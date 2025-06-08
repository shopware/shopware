---
title: Improve CMS display mode configuration and preview
issue: NEXT-38234
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: @Marcel Brode
---
# Administration
* Added help-texts and improved wording for display modes of cms options to clarify its effects 
* Changed preview of product-box to show the preview image correctly
* Changed selection of vertical alignment to be disabled if a selection can't change the result
* Changed default of property `has-text` in `sw-cms-product-box-preview` to `false`
* Changed removing alignment configuration on image, when "cropped" (formerly "cover") has been selected
___
# Storefront
* Changed conditions in `src/Storefront/Resources/views/storefront/element/cms-element-image.html.twig` to still display cropped/cover images, when alignments are set, despite changed admin behavior
