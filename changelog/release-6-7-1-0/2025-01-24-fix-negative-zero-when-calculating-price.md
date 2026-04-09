---
title: Fix negative zero when calculating price
issue: #6418
author: Jasper Peeters
author_email: jasper.peeters@meteor.be
author_github: JasperP98
---
# Core
* Changed `\Shopware\Core\Checkout\Cart\Price\CashRounding::mathRound` to always round a zero cash price to a positive zero.
