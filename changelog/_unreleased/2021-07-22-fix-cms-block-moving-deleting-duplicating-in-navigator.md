---
title: Fix CMS block moving, deleting, duplication in navigator
issue: NEXT-16314 
---
# Administration
* Added debouncing methods to the navigator of `sw-cms-detail`, to improve usability, when using the navigator
* Changed behaviour of CMS navigator, so that every action will trigger a page save
* Deprecated unused methods `cloneBlock` and `cloneSlotsInBlock` in `platform/src/Administration/Resources/app/administration/src/module/sw-cms/component/sw-cms-sidebar/index.js`