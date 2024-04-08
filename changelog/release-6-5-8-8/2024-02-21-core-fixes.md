---
title: Core fixes
issue: NEXT-33707
author: oskroblin Skroblin
author_email: o.skroblin@shopware.com
---

# Core
* Changed `ProductNoLongerAvailableEvent` behavior, event will only be triggered when the state changed and not always when the available flag got written
* Changed `messenger.routing.senders` config, to be merge correct when in the project custom routings are defined
* Changed `MigrationStep::updateDestructive` visibility from abstract to none-abstract
* Removed all `ADD COLUMN ... AFTER` statements in migrations, which cause performance issues 
* Changed `Es\CriteriaParser::parseSorting` to support nested sorting
* Added `AbstractElasticsearchDefinition::getIterator`, which allows to create an own iterator for the definition instead of a generic
