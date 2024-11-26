---
title: Change SalesChannelContext to extend Context
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopware\Core\System\SalesChannel\SalesChannelContext` to now extend `Shopware\Core\Framework\Context` instead of using use the Context as a field
* Removed duplicate methods from `Shopware\Core\System\SalesChannel\SalesChannelContext` which are now directly provided by the extended `Shopware\Core\Framework\Context`
