---
title: Improve product search keywords analyzer.
issue: NEXT-12664
flag: FEATURE_NEXT_10552
---
# Core
* Added new function `analyzeBaseOnSearchConfig` in interfaces `ProductSearchKeywordAnalyzerInterface` which used to analyzer the product keyword base on the config from the database.
* Added new function `addAll` in class `AnalyzedKeywordCollection` which used to add multiple `AnalyzedKeyword` into `AnalyzedKeywordCollection`.
