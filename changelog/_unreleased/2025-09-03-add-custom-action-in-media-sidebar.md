---
title: Add custom action in media sidebar
author: Quynh Nguyen
author_email: q.nguyen@shopware.com
author_github: quynhnguyen68
---
# Administration
* Added block `sw_media_quickinfo_quickactions_custom_action` in `src/module/sw-media/component/sidebar/sw-media-quickinfo/sw-media-quickinfo.html.twig` to show action buttons in media sidebar.
* Changed in `src/module/sw-media/component/sidebar/sw-media-quickinfo/index.js`
    * Added computed property `extensionSdkButtons` to show action buttons.
    * Added method `runAppAction` to call action button's callback function.

* Changed in `src/app/component/app/sw-app-action-button/sw-app-action-button.html.twig` to show meteor icon for action button.
