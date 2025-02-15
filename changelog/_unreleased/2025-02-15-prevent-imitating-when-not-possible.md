---
title: Prevent imitating as customer when not possible
issue: NEXT-40673
author: Melvin Achterhuis
author_email: melvin@achterhuis.work
author_github: @MelvinAchterhuis
---
# Administration
* Added customer active check in `computed` for `src/Administration/Resources/app/administration/src/module/sw-customer/component/sw-customer-card/index.js`.
* Added new `computed` called `customerImitationWarning` to determine the warning message in `src/Administration/Resources/app/administration/src/module/sw-customer/component/sw-customer-card/index.js`.
* Changed logic in for tooltip message and disabled in `src/Administration/Resources/app/administration/src/module/sw-customer/component/sw-customer-card/sw-customer-card.html.twig`.
* Added separate snippets for each scenario in for `module/sw-customer`.
