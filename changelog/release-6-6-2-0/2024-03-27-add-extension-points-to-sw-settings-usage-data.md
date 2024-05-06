---
title: Add extension points to sw-settings-usage-data
issue: NEXT-34664
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Administration
* Added component `src/module/sw-settings-usage-data/component/sw-settings-usage-data-general`
* Changed component `src/module/sw-settings-usage-data/page/sw-settings-usage-data`
  * to contain `sw-tabs` with position identifier `sw-settings-usage-data`
  * to show the new component `sw-settings-usage-data-general` as the default tab
