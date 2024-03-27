---
title: generate new token when context not found
issue: NEXT-00000
author: Jasper Peeters
author_email: jasper.peeters@meteor.be
author_github: JasperP98
---

# Core

* When you call the `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextService::get` and the context is not found, there will be a context created in memory with the given sales channel context token. You would expect a new token to be generated in this case.
