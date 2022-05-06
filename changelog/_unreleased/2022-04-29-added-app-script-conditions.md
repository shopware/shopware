---
title: Added app script conditions
issue: NEXT-19860
author: d.neustadt
author_email: d.neustadt@shopware.com
author_github: dneustadt
---
# Core
* Added entities `app_script_condition` and `app_script_condition_translation`
* Added field `scriptId` and association `appScriptCondition` to `RuleConditionDefinition`
* Added association `scriptConditions` to `AppDefinition`  
* Added `rule-conditions` and nodes contained therein to app manifest schema
___
# Administration
* Added `sw-condition-script` rule component
