---
title: Use clone API for sections and blocks
issue: NEXT-19624
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: @NiklasLimberg
---
# Administration
* Changed `onBlockDuplicate` and `onSectionDuplicate` in `sw-cms-detail/index.js` to use the clone API
* Deprecated `cloneBlockInSection`, `cloneSlotsInBlock` and `prepareSectionClone`, because they are superseded by the clone API
