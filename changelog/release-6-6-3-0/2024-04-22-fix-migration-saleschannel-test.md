---
title: Fix migration saleschannel test
issue: NEXT-00000
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Migration1620820321AddDefaultDomainForHeadlessSaleschannelTest` to catch `ForeignKeyConstraintViolationException`
