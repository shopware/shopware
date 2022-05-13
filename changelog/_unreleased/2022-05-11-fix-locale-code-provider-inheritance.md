---
title: Fix locale code provider on inherited locale code
author: Silvio Kennecke
author_github: @silviokennecke
---
# Core
*  Change `\Shopware\Core\System\Locale\LanguageLocaleCodeProvider::getLocaleForLanguageId` to also check the parent language
