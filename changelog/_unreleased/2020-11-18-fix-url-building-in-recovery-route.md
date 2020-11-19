---
title: Fix url building in recovery route
issue: NEXT-10398
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Changed `\Shopware\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute`, to fix url handling with leading slashes
