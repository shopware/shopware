---
title: fix AfterSort with multiple null values
issue: Ocarthon
author: Philip Standt
author_email: philip.standt@strix.net
author_github: @Philip Standt
---

# Core
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Util\AfterSort` to correctly sort collections with multiple null `afterId` values
