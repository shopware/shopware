---
title: Lower min version of composer package-versions-deprecated to 1.8.0
issue: NEXT-13530
---
# Core
* Changed version constraint `composer/package-versions-deprecated` from `^1.8.2` to `^1.8.0`. This will fix the update in projects that still have the package pinned to `1.8.0`. This is the case for all `shopware/production` versions before `v6.3.5.0`. 
