---
title: Improve SalesChannel id & context usage
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopware\Core\System\SalesChannel\SalesChannelContext` to reduce method calls usage
* Changed `Shopware\Core\System\SalesChannel\BaseContext` to add the `getSalesChannelId` method
* Changed multiple files that used `->getSalesChannel()->getId()` to now use `->getSalesChannelId()` instead
