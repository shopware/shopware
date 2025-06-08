---
title: Added new API route to update order addresses
issue: NEXT-31922
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: jozsefdamokos
---
# Core
* Added new `OrderAddressService` to handle order address updates.
___
# API
* Added new API route to update order addresses `/api/_action/order/{orderId}/order-address` which takes a mapping object as payload and updates the order addresses accordingly.
___
# Administration
* Changed the order details page to save order addresses using the new API route. 
