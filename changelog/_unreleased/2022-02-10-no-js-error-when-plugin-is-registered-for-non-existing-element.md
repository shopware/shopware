---
title: No JS error when plugin is registered for non existing element
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Only call `PluginManager_initializePluginOnElement` for existing elements to avoid JavaScript errors
