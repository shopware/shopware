---
title: Allow Storefront routes without prefix
author: Michael Telgmann
author_github: @mitelg
---
# Storefront

* Added the possibility to add the route name to the `storefront.router.allowed_routes` configuration in `storefront.yaml`.
  This allows the usage of routes without the `frontend`, `widgets` or `payment` prefix.

___

# Upgrade Information

## Allow custom route names for Storefront controllers

It is now possible to add custom route names for Storefront controllers in the `storefront.yaml` configuration file.
This allows the route to be identified as a Storefront route without the usage of the `frontend`, `widgets` or `payment` prefixes.
Add the following configuration to your `storefront.yaml` file:

```yaml
storefront:
    router:
        allowed_routes:
            - swag.test.foo-bar
```
