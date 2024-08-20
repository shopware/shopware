---
title: Made NavigationRoute validate function decorateable
date: 2024-07-05
area: core
---

# Core
* Added the new `NavigationRouteValidate` Event to make it possbile to set the NavigationRoute state valid based on logic in event subscribers.
* Moved the SalesChannel category id validation check to the `NavigationRouteValidateSubscriber` using the new `NavigationRouteValidateEvent`.
* Moved utility functions from the `NavigationRoute` to a generic `CategoryService` so this functions can be reused.