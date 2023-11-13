---
title: Add American English in Installer
issue: NEXT-24427
---
# Core
* Added new translation file in `src/Core/Installer/Resources/translations/translations/messages.us.yaml` to add English (United States of America) as a new supported languages for Installer
* Added a new migration in `\Shopware\Core\Migration\V6_5\Migration1675323588ChangeEnglishLocaleTranslationOfUsLocale` to change the translation of English US locale correctly
