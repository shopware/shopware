---
title: Make an array struct iterable and countable
issue: 
author: Dominik Mank
author_email: d.mank@web-fabric.de
author_github: dominikmank
---

# Core

* added `\IteratorAggregate` and `\Countable` to the `Shopware\Core\Framework\Struct\ArrayStruct` to allow iteration through the values and counting the values
* added test cases for the `Shopware\Core\Framework\Struct\ArrayStruct`
