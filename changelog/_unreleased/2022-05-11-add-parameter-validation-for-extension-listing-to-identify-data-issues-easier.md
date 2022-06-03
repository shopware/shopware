---
title: Add parameter validation for extension listing items to identify data issues easier
issue: NEXT-21703
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
*  Added validation of array keys in `\Shopware\Core\Framework\Store\Struct\ExtensionStruct::fromArray` to prevent future access on uninitialized properties error
