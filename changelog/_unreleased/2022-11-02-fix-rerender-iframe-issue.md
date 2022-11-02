---
title: Fix reloading iframes that causes duplicated elements in App
author: Vu Le
author_email: lednguyenvu@gmail.com
author_github: Vu Le
---
# Administration
* Changed `extension` watcher in `extension-api/sw-iframe-renderer/index.ts` to only re-assign `signedIframeSrc` whenever there are changes in the extension data.
