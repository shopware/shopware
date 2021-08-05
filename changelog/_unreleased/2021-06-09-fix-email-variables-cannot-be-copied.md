---
title: Fix email variables cannot be copied
issue: NEXT-15614
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com 
author_github: @lernhart
---
# Administration
* Changed method `onCopyVariable` in `sw-mail-template/page/sw-mail-template-detail/index.js` to allow copying to clipboard in non-https environments as well.
