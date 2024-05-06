---
title: Improved admin typescript implementation
issue: NEXT-33740
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Administration
* Added correct return type to `ApiService.handleResponse` in `api.service.ts`
* Changed `jsonapi-parser.service` to typescript
* Added global interface `ComponentHelper` for typing `Shopware.Components.getComponentHelpers()`
* Added global interface `CustomShopwareProperties` for typing additional properties added to `ShopwareClass`
