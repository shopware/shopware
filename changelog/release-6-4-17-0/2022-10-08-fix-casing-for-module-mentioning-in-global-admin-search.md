---
title: Fix casing for module mentioning in global admin search
issue: NEXT-23605
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Administration
* Added `entityNameLower` variable to `global.sw-search-more-results.labelShowResultsInModuleV2` to allow translations to use the original casing of an entity or a lower case version
* Changed `entityName` in `global.sw-search-more-results.labelShowResultsInModuleV2` to be the original casing instead of always lower case as it is more common to have upper-cased nouns
* Changed `global.sw-search-more-results.labelShowResultsInModuleV2` to use `entityNameLower` instead of `entityName` to keep lower cased entity name
