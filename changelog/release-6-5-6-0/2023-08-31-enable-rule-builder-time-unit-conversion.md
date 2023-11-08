---
title: enable rule builder time unit conversion
issue: NEXT-24941
author: Lars Kemper
author_email: l.kemper@shopware.com
author_github: @LarsKemper
---
# Core
* Changed type of property `daysPassed` to `float` in `src/Core/Framework/Rule/Container/DaysSinceRule.php`
* Added `unit` configuration to `getConfig` method in `src/Core/Framework/Rule/Container/DaysSinceRule.php`
___
# Administration
* Changed default time unit from `hr` to `d` in `src/module/sw-settings-rule/utils/unit-conversion.utils.ts`
