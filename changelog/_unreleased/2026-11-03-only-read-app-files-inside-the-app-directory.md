---
title: Only read App files inside the App directory
issue: https://github.com/shopware/shopware-private/issues/509
---

# Core

* Changed `Shopware\Core\Framework\Util\Filesystem::has()` and `hasFile()` to only accept paths inside the App directory.
  A path from the App manifest that leaves the App directory, such as an icon `../../.env`, is treated like a missing file, so the icon is skipped.
  This applies to the App icon, the icons of payment methods, shipping methods and flow actions, and the scripts of rule conditions.
* Changed `Shopware\Core\Framework\App\Cms\BlockTemplateLoader` to reject CMS blocks whose name leaves the `cms/blocks` directory of the App.
