---
title: Fix multiple recipients throwing type error.
issue: NEXT-16644
author: Sven Mäurer
author_email: s.maeurer@kellerkinder.de
author_github: Zwaen91
---
# Core
* Changed the arguments passed to `formatMailAddresses()` to fix passing multiple mail addresses for cc, bcc, reply and return path.
* Validate cc, bcc, reply and return path mail addresses.
