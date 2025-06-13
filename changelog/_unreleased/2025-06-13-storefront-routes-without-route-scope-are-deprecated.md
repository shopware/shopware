---
title: Storefront routes without route scope are deprecated
author: Michael Telgmann
author_github: @mitelg
---
# Storefront

* Deprecated storefront routes without route scope. Add a route scope to the route or add the route name to the `storefront.router.allowed_routes` configuration in `storefront.yaml`

___

# Upgrade Information

## Deprecation of Storefront routes without route scope

Storefront routes should always have a defined route scope from now on.
Not providing a route scope for routes with the prefixes `frontend.`, `widgets.` and `payment.` is deprecated.
If it is not possible to add a route scope to the route, you can add the route name to the `storefront.router.allowed_routes` configuration in the `storefront.yaml`.
