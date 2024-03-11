---
title: Indexing results in an exception, when a inherting language is used
issue: NEXT-34027
---
# Core
* Changed method `loadLanguages` in `src/Core/System/Language/LanguageLoader.php` to load the locale code of the inheriting language.
* Changed method `translated` in `src/Elasticsearch/Framework/ElasticsearchFieldBuilder.php` to use the correct locale code of the inheriting language.
