---
title: Remove first level app snippet restriction
issue: -
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: @Marcel Brode
---
# Administration
* Changed behaviour of `SnippetFinder::getAppAdministrationSnippets` to allow possible exact duplicates of snippet keys in apps, just like plugins
* Deprecated `SnippetException::SNIPPET_DUPLICATED_FIRST_LEVEL_KEY_EXCEPTION` and `SnippetException::duplicatedFirstLevelKey`, which will removed in 6.8 without replacement
