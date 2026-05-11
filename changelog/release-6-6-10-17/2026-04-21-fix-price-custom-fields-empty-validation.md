---
title: Fix empty price custom fields triggering change detection on product edit
issue: #7170
---
# Administration
* Changed `sw-form-field-renderer` to initialize empty price custom fields in the `data` hook instead of mutating `currentValue` in `createdComponent`, so the initial placeholder no longer emits `update:value` and marks products as changed when the form mounts.
