---
title: Fix variables not defined in `buy_widget_reviews` if parent block is overwritten
issue: NEXT-22951
author: Daniel Galla
author_email: d.galla@imi.de
author_github: DanieliMi
---
# Storefront
* Deprecates the variables `remoteClickOptions` and `reviewTabHref` for tag v6.5.0 
* Sets the variables `remoteClickOptions` and `reviewTabHref` in the scope of `buy_widget_reviews` if not defined and therefore fixes the expected behavior of the `buy_widget_reviews` block if the parent block (`buy_widget_data`) gets overwritten but original `buy_widget_reviews` is used via `{{ parent() }}` call
