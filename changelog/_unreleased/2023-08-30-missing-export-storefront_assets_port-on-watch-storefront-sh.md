---
title: Missing "export STOREFRONT_ASSETS_PORT" on watch-storefront.sh
issue: NEXT-30280
author: Matheus Gontijo
author_email: matheus@matheusgontijo.com
author_github: https://github.com/matheusgontijo
---

Missing "export STOREFRONT_ASSETS_PORT" on watch-storefront.sh

It's used later on few places, like `Storefront/Resources/app/storefront/build/proxy-server-hot/index.js`, but because it was not exported, the value is "undefined".
