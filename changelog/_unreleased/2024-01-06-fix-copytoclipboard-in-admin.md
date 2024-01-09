---
title: Fix copytoClipboard in admin
issue: NEXT-0000
author: tinect
author_email: s.koenig@tinect.de
author_github: tinect
---

# Administration

* Changed usage of method `copyToClipboard` from DomUtil to use the new `copyStringToClipboard` in modules `SwMediaMediaItem`, `SwFieldCopyable`, `SwMailTemplateDetail`, `SwMediaQuickinfo`, `SwSalesChannelDetailBase`, 
* Removed unused attribute `copy-able` in components `SwVeryfyUserModal`, `SwUserPermissionsUserListing`, `SwUsersPermissionsUserCreate` and `SwUserPermissionsUserDetail` and module `SwFirstRunWizardWelcome`
* Changed attribute `copy-able` to `copyable` in components `SwUserPermissionsUserDetail` to make field copyable
