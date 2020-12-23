---
title: Fix styling in custom field set renderer with inheritance and add inheritance to system config renderer
issue: NEXT-11437
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: @jleifeld
---
# Administration
* Added inheritance to `sw-system-config`
* Changed visual appearance of boolean and checkbox fields in `custom-field-set-renderer`
* Removed method `restoreInheritance` in `sw-checkbox-field`
* Added property `bordered` in `sw-checkbox-field`
* Removed property `bordered` in `sw-switch-field`
* Removed method `onInheritanceRestore` in `sw-switch-field`
* Changed default value of `hasParent` in `sw-inherit-wrapper` to `undefined`
