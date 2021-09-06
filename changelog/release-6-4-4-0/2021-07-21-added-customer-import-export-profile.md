---
title: Added customer import export profile
issue: NEXT-8196
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added migration for default customer import export profile
* Added `CountrySerializer` to resolve `Country` associations by `iso`
* Added `LanguageSerializer` to resolve `Language` associations by `locale.code`
* Added `CustomerSerializer` that resolves `CustomerGroup`, `PaymentMethod` and `SalesChannel` associations by `name`
