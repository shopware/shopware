---
title: Throw exception for requests in broken test environment
issue: NEXT-18917
author: Andreas Fernandez
author_email: a.fernandez@scripting-base.de
author_github: andreasfernandez
---
# Core
* Changed `Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour` to throw an exception if the request in `assignSalesChannelContext()` is erroneous
