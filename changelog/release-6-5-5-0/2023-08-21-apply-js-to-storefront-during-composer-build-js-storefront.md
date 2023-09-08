---
title: Apply JS to Storefront during composer build:js:storefront
issue: NEXT-30019
---
# Core
* Added `bin/console bundle:dump` and `bin/console theme:compile` to composer script `build:js:storefront` in order to consider apps/plugins and show the JS in the Storefront sales chanel
* Removed commands `bin/console bundle:dump` and `bin/console feature:dump` from composer script `build:js` as they are now already covered by the scripts `build:js:storefront` and `build:js:admin`
* Removed command `bin/console theme:compile` from composer scripts `setup` and `reset` as it is already covered by `@build:js` which is run beforehand
