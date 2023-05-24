---
title: Fix multiple recipients throwing type error.
issue: NEXT-25638
author: Sven Mäurer
author_email: s.maeurer@kellerkinder.de
author_github: Zwaen91
---
# Core
* Changed the arguments passed to `MailFactory::formatMailAddresses` to allow passing multiple mail addresses for cc, bcc, reply, and return path
* Changed method `MailFactory::create`  to validate cc, bcc, reply, and return path mail addresses
