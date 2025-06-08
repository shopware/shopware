---
title: Reduce request payload of cms listing
issue: NEXT-13859
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com 
author_github: @lernhart
---
# Administration
* Removed loading of associations (`sections` and `categories`) which produces extremely large responses in `sw-cms-layout-modal`
