---
title: Fix account order design in Bootstrap v5
issue: NEXT-15229
author: Tobias Berge
author_email: t.berge@shopware.com
author_github: @tobiasberge
---
# Storefront
* Deprecated `.table` class on `.order-table` elements in the following templates because it adds unneeded styling:
    * `Resources/views/storefront/page/account/index.html.twig`
    * `Resources/views/storefront/page/account/order-history/index.html.twig`
