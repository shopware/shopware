---
title: Do not open layout assignment when saving new CMS layouts
issue: NEXT-13175
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: @tobiasberge
---
# Administration
* Changed method `onDiscardChanges` to async in `sw-cms-layout-assignment-modal/index.js`
* Deprecated event `confirm` in `sw-cms-layout-assignment-modal/index.js::onConfirm`
* Deprecated block `sw_cms_layout_assignment_modal_confirm_changes_text_general` in `sw-cms-layout-assignment-modal.html.twig`
* Changed method `onSave` and removed the checks for `page.categories.length` and `previousRoute` in `sw-cms-detail/index.js`
* Deprecated event subscriber `@confirm` inside block `sw_cms_detail_layout_assignment_modal` in `sw-cms-detail.html.twig`
* Deprecated data prop `previousRoute` in `sw-cms-detail/index.js`
* Deprecated navigation guard `beforeRouteEnter` in `sw-cms-detail/index.js`
* Deprecated method `onConfirmLayoutAssignment` in `sw-cms-detail/index.js`
* Removed translation `sw-cms.components.cmsLayoutAssignmentModal.confirmChangesTextGeneral`
