---
title: Improve fetching of language information for SalesChannelContext
author: Michael Telgmann
author_github: @mitelg
---

# Core

* Changed `\Shopware\Core\System\SalesChannel\Context\BaseSalesChannelContextFactory::getLanguageInfo` so it directly uses the language repository for fetching the language information.
___

# Upgrade Information
## Improved fetching of language information for SalesChannelContext

The `\Shopware\Core\System\SalesChannel\Context\BaseSalesChannelContextFactory` now uses the language repository directly to fetch language information.
With the next major version the query with the title `base-context-factory::sales-channel` will no longer add the `languages` association.
