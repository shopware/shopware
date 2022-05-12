---
title: Fix locale code provider on inherited locale code
author: Silvio Kennecke
author_github: @silviokennecke
---
# Core
*  Add `IFNULL(code, parent.code)` check to  `\Shopware\Core\System\Language\LanguageLoader::loadLanguages()`. This ensures the locale code is also set if a child language inherits the locale code from its parent.
