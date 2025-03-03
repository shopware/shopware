---
title: Improving bulk edit for b2b toggles
issue: NEXT-40826
---
# Administration
* Changed `onChangeValue`, `onChangeToggle` methods in `src/Administration/Resources/app/administration/src/module/sw-bulk-edit/component/sw-bulk-edit-change-type-field-renderer/index.js` to add param `changeType` in emit `change-value`. It can distinguish which is `emit` from toggle change or value change. 
