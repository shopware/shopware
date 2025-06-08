---
title: Fix quick opening and closing of OffCanvas cart
issue: NEXT-24497
---
# Storefront
* Deprecated parameter `closable` on method `setContent` in `offcanvas.plugin.js`. The `closable` parameter will be set by the `open` method only instead.
* Removed parameter `closeable` from private method `_registerEvents` in `offcanvas.plugin.js`.
* Added parameter `closeable` to private method `_createOffCanvas` in `offcanvas.plugin.js`.
* Changed method `_registerEvents` and removed workaround to enable `closeable` option. The `closeable` option is now used inside `createOffCanvas` and will utilize the `static` option of the Bootstrap OffCanvas.
