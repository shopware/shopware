---
title: Fix updates for Composer plugins
issue: #6964
---
# Core
* Added `$allowUpdate` property to ExtensionStruct to also allow updates for plugins that set `executeComposerCommands` to true, but are still installed under `custom/plugins`. This activates the update plugin button also for the commercial plugin again.
