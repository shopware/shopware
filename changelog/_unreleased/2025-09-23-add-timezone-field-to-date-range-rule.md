---
title: Add timezone field to date range rule
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Administration
* Added `timezone` value and validation to `Resources/app/administration/src/app/component/rule/condition-type/sw-condition-date-range/index.js`
* Added `timezone` field to `Resources/app/administration/src/app/component/rule/condition-type/sw-condition-date-range/sw-condition-date-range.html.twig`
___
# Core
* Added `timezone` property to `Framework/Rule/DateRangeRule.php` and changed the date time format to `Y-m-d\TH:i:s`
