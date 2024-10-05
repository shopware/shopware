---
title: Fix the selected customer when creating a new order
issue: NEXT-36826
author: Moritz Müller
author_email: moritz@momocode.de
author_github: @momocode-de
---

# Administration
* Changed method `navigateToCreateOrder` in `sw-customer-detail-order` component to pass the customer ID as a query parameter instead of passing the customer object as a route param.
* Changed method `createdComponent` in `sw-order-create-initial` component to load the customer object by ID instead of reading the customer object from route params.
