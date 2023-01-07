---
title: Fix double opt-in account reactivation
issue: NEXT-23771
author: Silvio Kennecke
author_email: development@silvio-kennecke.de
author_github: @silviokennecke
---
# Core
* Changed `\Shopware\Core\Checkout\Customer\SalesChannel\RegisterConfirmRoute::confirm` to verify if the customer has to double-opt-in and whether the account is already confirmed
