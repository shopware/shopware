---
title: Case insensitive guest order e-mail and postal code check
issue: #8686
---
# Core
* Changed `\Shopware\Core\Checkout\Order\SalesChannel\OrderRoute::checkGuestAuth`. Compare the guest's e-mail address with `strtolower` and the postal code with `strtoupper` to avoid small typing differences.
