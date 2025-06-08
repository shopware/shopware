---
title: Add Criteria to OrderRouteRequestEvent call
issue: NEXT-14492
author: Component K Corp
author_email: k@componentk.com
author_github: @augsteyer
---
# Storefront
* Changed method `loadNewestOrder` in `Storefront/Page/Account/Overview/AccountOverviewPageLoader.php` to provide Criteria class to the dispatched `OrderRouteRequestEvent` event
