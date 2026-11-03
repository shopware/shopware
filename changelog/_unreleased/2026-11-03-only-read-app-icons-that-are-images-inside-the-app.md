---
title: Only read App icons that are images inside the App directory
issue: https://github.com/shopware/shopware-private/issues/509
---

# Core

* Changed `Shopware\Core\Framework\Util\Filesystem::has()` and `hasFile()` to only accept paths inside the App directory, also after resolving symlinks.
  A symlinked App directory keeps working.
* Added `Shopware\Core\Framework\Util\Filesystem::readImage()` and `fileSize()`.
  App, payment method, shipping method and flow action icons must be images of at most 1 MB, other files are skipped.
* Added `Migration1793694040ClearInvalidAppIcons`, which removes stored App icons that are not images.
  Run `bin/console media:delete-unused` afterwards to delete unlinked payment and shipping method icon media.
