---
title: Reset from & to dates to ignore time when comparing dates for DateRangeRules
issue: NEXT-29662
author: Tommy Quissens
author_email: tommy.quissens@meteor.be
author_github: @quisse
---
# Core
* Changed method `match` in `src/Core/Framework/Rule/DateRangeRule.php` to ignore time when comparing dates
