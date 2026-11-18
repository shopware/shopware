---
title: Only read App files inside the App directory
issue: https://github.com/shopware/shopware-private/issues/509
---

# Core

* Changed `Shopware\Core\Framework\Util\Filesystem::has()` and `hasFile()` to only accept paths inside the App directory.
  Files outside of the App directory are treated like a missing file, so the icon is skipped.
  The theme filesystem of plugins is not restricted, because plugin themes may reference files outside of the plugin, e.g. in `node_modules`.
  This applies to the App icon, the icons of payment methods, shipping methods and flow actions, and the scripts of rule conditions.
* Changed `Shopware\Core\Framework\App\Cms\BlockTemplateLoader` to reject CMS blocks whose name leaves the `cms/blocks` directory of the App.
