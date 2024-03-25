---
title: Save billing address as a new order address with new id on order-creation to prevent a bug when editing order-addresses
issue: NEXT-31733
author: Marcus Müller
author_email: 25648755+M-arcus@users.noreply.github.com
author_github: @M-arcus
---
# Core
* Changed `OrderConverter::convertToOrder` to save the billing address as a new order address with new id on order-creation to prevent a bug when editing order-addresses
