---
title: Fix locale code provider on inherited locale code
author: Silvio Kennecke
author_github: @silviokennecke
---
# Core
*  Add locale code resolver to  `\Shopware\Core\System\Language\LanguageLoader`. This ensures the locale code is also set if a child language inherits the locale code from its parent.
