---
title: Change token instead of deleting it for cart restore
issue: NEXT-35111
author: Soner Sayakci
author_email: s.sayakci@shopware.com
---
# Core
* Changed `Shopware/Core/Checkout/Customer/SalesChannel/LogoutRoute` to rename the sales channel token to fix cart restoring after login
