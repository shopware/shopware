---
title: Fix language foreign key constraint error when changing default language with inherited languages
issue: 11025
---
# Core
* Changed `LanguageExceptionHandler` to also catch SQLSTATE 23000 error code 1451 (foreign key constraint violation when deleting/updating parent row) in addition to existing error codes 1217 and 1216.
___
# Next Major Version Changes

## Core
* The `LanguageExceptionHandler::matchException()` method now also catches error code 1451 for foreign key constraint violations when trying to change the default language with inherited languages present.