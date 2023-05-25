---
title: Added exception on invalid promotion code pattern
issue: NEXT-27046
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Added exception on invalid promotion code pattern
* Deprecated the following exceptions in replacement for Domain Exceptions
    * `Shopware\Core\Checkout\Promotion\Exception\PatternNotComplexEnoughException`
    * `Shopware\Core\Checkout\Promotion\Exception\PatternAlreadyInUseException`
