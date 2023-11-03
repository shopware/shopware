---
title: fix app rule condition validation
issue: NEXT-30809
author: Malte Janz
author_email: m.janz@shopware.com
author_github: MalteJanz
---
# Core
* Changed `Core/Content/Rule/RuleValidator.php` to validate `ScriptRule`'s correctly
* Removed protected `$count` property of `Core/Framework/Rule/Container/OrRule.php` which was added by mistake
* Removed protected `$customerGroupIds` property of `Core/Framework/Rule/ScriptRule.php` which was added by mistake
* Added `assignValues` and `getValues` public methods to `Core/Framework/Rule/ScriptRule.php` to adjust the values passed to the app script context
