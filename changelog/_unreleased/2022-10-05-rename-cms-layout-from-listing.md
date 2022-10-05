---
title: Rename CMS layouts from listing
issue: #2678
author: Lisa Meister
author_email: lisa.meister.93@gmx.de
author_github: Lilibell

___
# Administration
* Add `sw-context-menu-item`for initiating layout rename to `sw-cms-list.html.twig`
* Add `sw-modal` for choosing a new layout name to `sw-cms-list.html.twig`
* Add props `showRenameModal` and `newName` to `sw-cms-list`
* Add methods `onRenameCmsPage`, `onCloseRenameModal` and `onConfirmPageRename` to `sw-cms-list`
* Add snippets `sw-cms.components.cmsListItem.modal.renameModalTitle`, `sw-cms.components.cmsListItem.modal.textRenameConfirm`,
`sw-cms.components.cmsListItem.modal.buttonRename` and `sw-cms.components.cmsListItem.rename`
