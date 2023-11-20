---
title: Deprecated association auto-loading in SalesChannelDefinition
issue: NEXT-25328
author: Krzykawski
author_email: m.krzykawski@shopware.com
author_github: Krzykawski
---
# Core
* Added preparation for upcoming autoload removal of `analytics` association in `Shopware\Core\System\SalesChannel\SalesChannelDefinition`
___
# Upgrade Information
If you are relying on the `sales_channel.analytics` association, please associate the definition directly with the criteria because we will remove autoload from version 6.6.0.0.
