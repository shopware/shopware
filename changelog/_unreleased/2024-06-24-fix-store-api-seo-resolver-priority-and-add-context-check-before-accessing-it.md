---
title:          Fix StoreApiSeoResolver priority and add context check before accessing it
issue:          NEXT-36924
author:         Marcel Romeike
author_email:   m.romeike@basecom.de
author_github:  @mromeike
---
# Core
* Changed priority of `\Shopware\Core\Content\Seo\SalesChannel\StoreApiSeoResolver::addSeoInformation()`
  from `10_000` to `11_000`
* Added a check for availability of `sw-sales-channel-context` before accessing it
  in `src/Core/Content/Seo/SalesChannel/StoreApiSeoResolver.php`
* Added tests covering the `auth_required=false` store-api route edge-case
