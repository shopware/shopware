---
title: Mark migrations as internal
issue: NEXT-23541
---
# Core
* Deprecated all DB migration steps as they will become @internal in v6.5.0.0.
___
# Next Major Version Changes
## Internal Migrations
All DB migration steps are now considered `@internal`, as they never should be extended or adjusted afterwards.
