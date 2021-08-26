---
title: Add `system.plugin_upload` privilege
issue: NEXT-15671
---
# Core
- Added `system.plugin_upload` privilege
- Changed `\Shopware\Core\Framework\Store\Api\ExtensionStoreActionsController` to require the new privilege for the extension upload
___
# Administration
- Changed the `sw-extension-my-extensions-index` component to only show the file upload button if the user has the `system.plugin_upload` privilege
- Changed the `sw-extension-file-upload` component to show a confirm dialogue before opening the file upload
