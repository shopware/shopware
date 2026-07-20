---
title: Copy context rules to ESI request context
author: Benedikt Schulze Baek
author_email: b.schulze-baek@shopware.com
author_github: @bschulzebaek
---
# Core
* Changed `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextService::get` to copy context rules to the context of ESI requests instead of skipping them.
