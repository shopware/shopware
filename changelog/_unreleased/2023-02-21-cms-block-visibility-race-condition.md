---
title: CMS Block visibility race condition
issue: NEXT-25495
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Changed the `cmsDataResolver.service` to add fallback visibility settings to sections and blocks
* Added method `onVisibilityChange` in `sw-cms-visibility-config` to throw an event when the user changes the visibility settings of a block.
* Added method `onVisibilityChange` in `sw-cms-sidebar` to set the visibility for the selected section or block .
