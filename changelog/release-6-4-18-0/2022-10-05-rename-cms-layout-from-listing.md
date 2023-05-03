---
title: Rename CMS layouts from listing
issue: NEXT-23828
author: Lisa Meister
author_email: lisa.meister.93@gmx.de
author_github: @Lilibell
---
# Administration
* Added `sw-context-menu-item`for initiating layout rename to `sw-cms-list.html.twig`
* Added `sw-modal` for choosing a new layout name to `sw-cms-list.html.twig`
* Added props `showRenameModal` and `newName` to `sw-cms-list`
* Added methods `onRenameCmsPage`, `onCloseRenameModal` and `onConfirmPageRename` to `sw-cms-list`
* Added snippets `sw-cms.components.cmsListItem.modal.renameModalTitle`, `sw-cms.components.cmsListItem.modal.textRenameConfirm`, `sw-cms.components.cmsListItem.modal.buttonRename` and `sw-cms.components.cmsListItem.rename`
