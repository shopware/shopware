---
title: Fix negative zero when calculating price
issue: NEXT-00000
author: Jasper Peeters
author_email: jasper.peeters@meteor.be
author_github: JasperP98
---

# Core

* When rounding the cash price in `\Shopware\Core\Checkout\Cart\Price\CashRounding::mathRound` the result should always result in a positive zero.
