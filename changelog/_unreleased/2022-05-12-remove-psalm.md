---
title: Remove psalm
issue: NEXT-21494
---
# Core
* Removed `psalm` static analysis from CI, now only `phpstan` will be used.
* Deprecated the `psalm` dependency, the dependency will be removed with v6.5.0.0. If your project uses the `psalm` dependency from the core, please install the package directly in your project.
___
# Next Major Version Changes
## Removal of the `psalm` dependency

The platform does not rely on `psalm` for static analysis anymore, but solely uses `phpstan` for that purpose.
Therefore, the `psalm` dev-dependency was removed. 
If you used the dev-dependency from platform in your project, please install the `psalm` package directly into your project.
