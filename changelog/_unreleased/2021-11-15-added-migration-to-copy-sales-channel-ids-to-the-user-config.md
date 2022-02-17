---
title: Added migration to copy sales channel ids to the user config
issue: NEXT-18558
author: Martin Krzykawski
author_email: m.krzykawski@shopware.com 
---
# Core
* Added migration to copy seven or less sales channel ids to the user_config table, which are alphabetically sorted by the translated name depending on the selected language of the user
* Added 'sales-channel-favorites' as key for the user config to save sales channels as favorites
