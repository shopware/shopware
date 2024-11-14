---
title: Removal of obsolete method in DefinitionValidator
issue: NEXT-38579
author: Michael Telgmann
author_github: @mitelg
---
# Core
* Deprecated method `\Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator::getNotices`. It will be removed without replacement
___
# Upgrade Information
## Deprecation of obsolete method in DefinitionValidator
The method `\Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator::getNotices` is deprecated and will be removed without replacement.
It always returns an empty array, so it has no real purpose.
___
# Next Major Version Changes
## Removal of obsolete method in DefinitionValidator
The method `\Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator::getNotices` was removed.
