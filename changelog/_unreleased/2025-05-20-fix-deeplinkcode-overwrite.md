---
title: Fix incorrect overwrite of deepLinkCode on order recalculation
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: @mstegmeyer
---
# Code
* Changed `CartTransformer` to not overwrite the `deeplinkCode` of an order when not requested.
