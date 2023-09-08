---
title: Order creation without customer breaks admin order panel
issue: NEXT-29131
---
# Administration
* Changed `sw-order-list.html.twig` to check whether `order.orderCustomer` is exists or not before rendering customer's name.
