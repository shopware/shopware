---
title: Fix customer logout process will lose all sessions
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Changed `Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute` to no longer replace the current `sales_channel_api_context` in the database (This will prevent multiple session logouts)
* Changed `Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute` to correctly dispatch the `SalesChannelContextTokenChangeEvent` for the context token switch
