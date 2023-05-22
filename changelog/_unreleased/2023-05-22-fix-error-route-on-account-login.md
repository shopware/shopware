---
title: Fix error redirect on account login
issue: NEXT-26995
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---

# Core
* Deprecated the constructor of the following exceptions, as there now is a domain exception in `Shopware\Core\Framework\Routing\RoutingException`
  * `Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException`
  * `Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException`
