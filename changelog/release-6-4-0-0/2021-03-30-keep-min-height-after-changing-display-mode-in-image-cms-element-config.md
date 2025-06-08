---
title: Keep min height after changing display mode in image CMS element config
issue: NEXT-14468
---
# Administration
* Changed method `onChangeDisplayMode` and removed the else-block which set the `minHeight` value of the CMS element config to an empty string inside the following components:
    * `Resources/app/administration/src/module/sw-cms/elements/image/config/index.js`
    * `Resources/app/administration/src/module/sw-cms/elements/image-gallery/config/index.js`
    * `Resources/app/administration/src/module/sw-cms/elements/image-slider/config/index.js`
