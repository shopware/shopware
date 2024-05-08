---
title: Do not process promotions when cart price is zero
issue: NEXT-00000
author: Jasper Peeters
author_email: jasper.peeters@meteor.be
author_github: JasperP98
---

# Core

* Added check to prevent promotions from being processed when the cart price is zero, this will prevent promotions from being applied to free products. Otherwise the system will throw a 500 error when trying to apply an absolute promotion to a free product.
