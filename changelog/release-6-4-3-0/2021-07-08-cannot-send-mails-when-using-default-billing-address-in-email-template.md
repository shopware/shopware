---
title: Fix can't send mails with a default billing address in the customer registration email template 
issue: NEXT-15631
---
# Core
* Changed function `Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute.php::register` to add association `defaultBillingAddress`.
