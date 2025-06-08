---
title: Add technical name to import/export profiles
issue: NEXT-33627
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: jozsefdamokos
---
# Core
* Added technical name to import/export profiles. This name is used to identify the profile in CLI context and is unique.
* Deprecated the `name` field in `ImportExportProfileEntity` and getter and setter methods.
* Deprecated `profile` attribute in `ImportEntityCommand`. Use `--profile-technical-name` instead.
___
# Next Major Version Changes
## Removal of deprecations
* Removed method `ImportExportProfileEntity::getName()` and `ImportExportProfileEntity::setName()`. Use `getTechnicalName()` and `setTechnicalName()` instead.
* Removed `profile` attribute from `ImportEntityCommand`. Use `--profile-technical-name` instead.
* Removed `name` field from `ImportExportProfileEntity`.
