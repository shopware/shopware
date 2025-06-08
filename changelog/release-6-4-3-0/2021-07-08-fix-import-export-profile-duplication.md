---
title: Fix-Import-Export-Profile-Duplication
issue: NEXT-12338
author: Malte Janz
author_email: m.janz@shopware.com 
author_github: Malte Janz
---
# Administration
* Changed the `onDuplicateProfile(item)` method in the `sw-import-export-view-profiles` component: it now uses the `repositoy.clone(...)` function instead of manually cloning the entity.
* Changed the `saveSelectedProfile()` method in the `sw-import-export-view-profiles` component: it now saves the profile entity with the currently selected content language as expected instead of the system language.
* Added `isNotSystemLanguage` and `createTooltip` computed properties for the `sw-import-export-view-profiles` component and use them in the profile add button.
