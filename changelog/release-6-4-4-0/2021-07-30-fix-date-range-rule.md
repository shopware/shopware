---
title: Fix the date range rule in the rule builder
issue: NEXT-16524
author: Manuel Kress
author_github: windaishi
author_email: 6232639+windaishi@users.noreply.github.com
---
# Core
* Fixed the class `Shopware\Core\Framework\Rule\DateRangeRule` to correctly evaluate date and time ranges.
    * The date and time limits are now matched correctly.
    * If no timestamp is used, the end date is now matched correctly.
    * Several tests were added to ensure the functionality of this class.
* Added method `getCurrentTime()` to `Shopware\Core\Framework\Rule\RuleContext`.
