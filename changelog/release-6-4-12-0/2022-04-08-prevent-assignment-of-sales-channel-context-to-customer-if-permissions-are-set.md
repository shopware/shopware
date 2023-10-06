---
title: Prevent assignment of sales channel context to customer if permissions are set
issue: NEXT-21315
author: Nils Evers
author_email: evers.nils@gmail.com
author_github: NilsEvers
---
# Core
* Changed `\Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute::switchContext` to only assign the `SalesChannelContext` to a customer if no permissions are set on the context. This prevents customers from being able to obtain permissions from an API call. 
