---
title: Deprecate active language and currency from header pagelet
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Deprecated `Shopware\Storefront\Pagelet\Header\HeaderPagelet::{getActiveLanguage,getActiveCurrency}` as they should be accessed through the context
