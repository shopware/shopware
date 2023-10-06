---
title: Google Product Feed - handle Articles without an Image 
issue: NEXT-18930
author: wolf128058
author_email: jonas.hess@mailbox.org
author_github: wolf128058
---
# Storefront
* Changed: `src/Administration/Resources/app/administration/src/module/sw-sales-channel/product-export-templates/google-product-search-de/body.xml.twig` Wrapping the cover.media-call in a check for "is defined". And skipping this image  if there is no image. So the feed for Google Products does not fail completely if only one product has no cover image.
