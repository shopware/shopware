---
title: Fix unknownRedisConnection adapter exception message
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed the error message in `unknownRedisConnection` of `AdapterException` to print the correct config path
