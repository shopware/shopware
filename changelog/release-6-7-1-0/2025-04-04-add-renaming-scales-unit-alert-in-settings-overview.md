---
title: Add renaming scales unit banner in Settings overview
issue: #8226
author: Le Nguyen
author_email: nguyenquocdaile@gmail.com
author_github: @nguyenquocdaile
---
# Administration
* Added `mt-banner` element to show banner info for settings overview in `src/module/sw-settings/page/sw-settings-index/sw-settings-index.html.twig`
* Added `hideSettingRenameBanner` data property to hide the banner
* Added `getUserConfig` method to load user config
* Added `onCloseSettingRenameBanner` method to close the banner
in `src/module/sw-settings/page/sw-settings-index/index.js`.
