---
title: Improve fetching of language information for SalesChannelContext
author: Michael Telgmann
author_github: @mitelg
---

# Core

* Changed `\Shopware\Core\System\SalesChannel\Context\BaseSalesChannelContextFactory::getLanguageInfo` so it will directly uses the language repository for fetching the language information with the next major version.
