---
title: Shopping cart is emptied after login of a B2B employee
issue: NEXT-37992
---
# Core
* Changed `Shopware\Core\System\SalesChannel\Context\CartRestorer::restoreByToken` to not ignore the current cart when restoring the cart of an employee.
