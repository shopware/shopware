---
title: Preserve administration sidebar state
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Changed `src/Administration/Resources/app/administration/src/app/store/admin-menu.store.ts` to also store the `expanded` state on change in the local storage. When the admin menu store is loaded, the initial `expanded` value is taken from the local storage.
