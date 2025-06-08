---
title: Fix translator always translates to default locale
issue: NEXT-13430
author_github: @Dominik28111
---
# Core
* Added optional parameter `$locale` to `Shopware\Core\Framework\Adapter\Translation\Translator::getSnippetSetId` to consider custom locale.
