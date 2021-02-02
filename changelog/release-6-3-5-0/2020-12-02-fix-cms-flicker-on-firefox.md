---
title: Fix cms flicker on firefox
issue: NEXT-6596
author: Jannis Leifeld
author_email: jannis.leifeld@googlemail.com 
author_github: @jleifeld
---
# Administration
* Changed css property `overflow` in `.sw-cms-sidebar__block-selection` in `sw-cms-sidebar` to `scroll`. This prevents flickering in firefox caused by the scrollbars
