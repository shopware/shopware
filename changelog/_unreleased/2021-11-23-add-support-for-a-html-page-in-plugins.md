---
title: Add support for a html page in plugins
issue: NEXT-18120
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: Jannis Leifeld
---
# Administration
* Added the possibility to use a HTML page in your plugins. To do this you just need to create a `index.html` file in the `src/Resources/app/administration` folder in your plugin. The page can be accessed by this URL `http://your-shop.com/bundles/yourplugin/administration/index.html`. This can be useful for the new ExtensionAPI which will be developed soon. It allows using App functionalities inside normal plugins.
