---
title: Save changes modal when having unsaved changes
issue: NEXT-18634
---
# Administration
* Added a new component `sw-flow-leave-page-modal` in `/module/sw-flow/component/modals` to show the warning modal if user unsaved the changes.
* Changed in `module/sw-flow/page/sw-flow-detail/index.js`:
    - Added `showLeavePageWarningModal`, `nextRoute` data to show warning modal.
    - Added new computed `flowChanges` to check if has any changes.
    - Added new hook `beforeRouteLeave` to handle User leaves to the page.
    - Added methods `onLeaveModalClose`, `onLeaveModalConfirm` to handle action on the warning modal.
* Added new block `sw_flow_leave_page_modal` in `/module/sw-flow/page/sw-flow-detail/sw-flow-detail.html.twig`.
