---
title: Deprecate recurring data struct properties
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Changed `Shopware\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct` to deprecate `subscriptionId` and `nextSchedule` properties due to being specific to the commercial implementation.
