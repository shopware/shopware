---
title: Change language inheritance
issue: NEXT-26889
---
# Core
* Added migration `Shopware\Core\Migration\V6_6\Migration1712309989DropLanguageLocaleUnique`
* Removed Unique index `uniq.translation_code_id` of table `language`

___
# Administration
* Changed the language settings module to allow using the same iso codes on different languages.
