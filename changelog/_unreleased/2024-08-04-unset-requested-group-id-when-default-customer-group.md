---
title: Don't set requestedGroupId when it's the sales channel default customer group
issue: NEXT-0000
author: Melvin Achterhuis
author_email: melvin.achterhuis@gmail.com
author_github: @MelvinAchterhuis
---

# Core
* Changed `src/Core/Checkout/Customer/SalesChannel/RegisterRoute.php` to unset requestedGroupId when it equals to groupId.
