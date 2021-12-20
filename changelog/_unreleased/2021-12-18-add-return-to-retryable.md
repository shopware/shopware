---
title: Add RetryableTransactions return value
issue: NEXT-19350
author: Ulrich Thomas Gabor
author_email: ulrich.thomas.gabor@odd-solutions.de
author_github: @UlrichThomasGabor
---
# Core
* `RetryableTransaction::retryable` now returns the return value of the passed in closure mirroring the behavior of Doctrine.

