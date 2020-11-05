---
title:              Optimise requests in LocaleToLanguageService
issue:              NEXT-10928
author:             Hannes Wernery
author_email:       hannes.wernery@pickware.de
author_github:      @hanneswernery
---
# Administration
* Changes the requests in `LocaleToLanguageService` so send a single request when calling `localeToLanguage()`.
