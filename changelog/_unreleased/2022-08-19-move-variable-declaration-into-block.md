---
title: Move reviews relevant variables declaration into reviews block scope
issue: -
author: Daniel Galla
author_email: d.galla@imi.de
author_github: DanieliMi
---
# Storefront
* Moves the variables `remoteClickOptions` and `reviewTabHref` into the scope of `buy_widget_reviews` and therefore fixes the expected behavior of the `buy_widget_reviews` block if the parent block (`buy_widget_data`) gets overwritten but original `buy_widget_reviews` is used via `{{ parent() }}` call
---
