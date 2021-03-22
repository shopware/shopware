---
title: Reduce request payload of cms listing
issue: NEXT-11253
author: Jannis Leifeld
author_email: jannis.leifeld@googlemail.com 
author_github: @jleifeld
---
# Administration
* Removed loading of associations (`sections` and `categories`) which produces extremely large responses in `sw-cms-list`
