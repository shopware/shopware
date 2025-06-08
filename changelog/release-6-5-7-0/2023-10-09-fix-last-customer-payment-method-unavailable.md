---
title: Fix last customer payment method unavailable
issue: NEXT-30753
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Core
* Changed `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory` to use Sales Channel Payment Method if customers last payment method is not active or assigned.
