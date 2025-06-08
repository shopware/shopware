---
title: Add countryState association to mail and document generation
issue: NEXT-15584
author: Malte Janz
author_email: m.janz@shopware.com
author_github: MalteJanz
---
# Core
* Changed the criteria in the following files by adding associations for fetching the `countryState` along with an OrderAddress when loading orders to generate mail or document templates:
  * `src/Core/Checkout/Cart/SalesChannel/CartOrderRoute.php`
  * `src/Core/Checkout/Order/Listener/OrderStateChangeEventListener.php`
  * `src/Core/Checkout/Document/DocumentService.php`
