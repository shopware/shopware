---
title: Show extension name on config page
issue: NEXT-19195
author: Maike Sestendrup
author_email: m.sestendrup@shopware.com 
---
# Administration
* Added `extension` data attribute to `src/module/sw-extension/page/sw-extension-config/index.js`
* Changed `src/module/sw-extension/page/sw-extension-config.html.twig` to show the `extension.label` instead of `namespace` and also display the extensions icon and producer
* Changed `src/app/component/meteor/sw-meteor-page/sw-meteor-page.scss` and increased the margin between the icon and title
