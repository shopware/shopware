---
title: Make an array struct iterable and countable
issue: NEXT-26250
author: Dominik Mank
author_email: d.mank@web-fabric.de
author_github: dominikmank
---

# Core

* Added `\IteratorAggregate` and `\Countable` to the `Shopware\Core\Framework\Struct\ArrayStruct` to allow iteration through the values and counting the values
* Added test cases for the `Shopware\Core\Framework\Struct\ArrayStruct`
