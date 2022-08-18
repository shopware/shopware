---
title: make used HttpCache-class customizable
issue: NEXT-22356
author: Sven Herrmann
author_email: sven.herrmann@ianeo.de
author_github: @SvenHerrmann
---
# Core
* Added static property `$httpCacheClass` to `src/Core/HttpKernel.php` to make the used HttpCache-class customizable (similar to Kernel-class)
