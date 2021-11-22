---
title: Only check for valid UUID if values are strings in theme configuration
issue: NEXT-18275
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed `Shopware\Storefront\Theme\ConfigLoader\DatabaseConfigLoader::resolveMediaIds` to only check for valid UUID if values are strings in theme configuration.
  
