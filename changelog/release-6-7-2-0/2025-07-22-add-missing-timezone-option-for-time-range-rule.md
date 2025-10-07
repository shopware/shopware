---
title: Add missing timezone option for TimeRangeRule
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Added `timezone` option to `Shopware\Core\Framework\Rule\TimeRangeRule`
___
# Administration
* Changed `Resources/app/administration/src/app/component/rule/condition-type/sw-condition-time-range/sw-condition-time-range.html.twig` to display the timezone option
* Changed `Resources/app/administration/src/app/component/rule/condition-type/sw-condition-time-range/index.js` to handle the new timezone option
