---
title: Deprecate DISABLE_EXTENSIONS and add PERFORMANCE_TWEAKS flag
issue: NEXT-40014
---

# Core

* Deprecated environment variable `DISABLE_EXTENSIONS`, it will be removed next major. [Switch to read-only extension manager instead](https://developer.shopware.com/docs/guides/hosting/installation-updates/extension-managment.html#configuring-extension-manager-to-read-only-in-admin)
* Added environment variable `PERFORMANCE_TWEAKS` to enable breaking changes for performance improvements.
