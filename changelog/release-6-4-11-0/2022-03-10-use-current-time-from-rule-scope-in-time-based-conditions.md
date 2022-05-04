---
title: Use current time from rule scope in time based conditions
issue: NEXT-20438
author: d.neustadt
author_email: d.neustadt@shopware.com
author_github: dneustadt
---
# Core
* Changed `TimeRangeRule` and `WeekdayRule` to call `RuleScope::getCurrentTime` to get the current time
