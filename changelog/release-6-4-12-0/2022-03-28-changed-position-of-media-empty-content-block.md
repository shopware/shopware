---
title: Changed position of media empty content block
issue: NEXT-20573
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Added the computed `shouldDisplayEmptyState` to move the logic out of `/sw-media-library.html.twig`
* Added z-index to `.sw-media-library__parent-folder` and `.sw-media-library__options-container` in `sw-media-library.scss`
