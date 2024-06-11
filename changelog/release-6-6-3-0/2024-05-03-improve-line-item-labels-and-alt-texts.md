---
title: Improve line-item labels and alt texts
issue: NEXT-33683
---
# Storefront
* Added missing fallback `alt` text on line-item thumbnail in `Resources/views/storefront/component/line-item/element/image.html.twig` when no alt text is provided in the media manager.
* Changed `aria-label` of line-item remove button to specify which line-item should be removed in `Resources/views/storefront/component/line-item/element/remove.html.twig`.