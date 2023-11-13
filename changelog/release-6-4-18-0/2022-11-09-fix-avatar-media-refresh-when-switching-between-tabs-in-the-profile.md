---
title: Fix avatar media refresh when switching between tabs in the profile
issue: NEXT-22610
---
# Administration
* Changed `resetGeneralData` method in `src/Administration/Resources/app/administration/src/module/sw-profile/page/sw-profile-index/index.js` to fix the media avatar to set null.
