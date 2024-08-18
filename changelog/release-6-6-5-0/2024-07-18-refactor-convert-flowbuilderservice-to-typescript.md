---
title: refactor & convert flowBuilderService to typescript
issue: NEXT-37264
author: Lars Kemper
author_email: l.kemper@shopware.com
author_github: @LarsKemper
---
# Administration

### `flow-builder.service.ts` Implementation Update

* Changed `flowBuilderService` to TypeScript and renamed file to `flow-builder.service.ts` located at `src/Administration/Resources/app/administration/src/module/sw-flow/service/flow-builder.service.ts`.
* Changed `flowBuilderService` function into a class `FlowBuilderService`.
* Changed all functions to class methods and applied type annotations.

### Additional Methods Added to `FlowBuilderService`

* Added `isKeyOfActionName()`
    * Checks if the given key is a valid action name.
* Added `isKeyOfActionLabel()`
    * Checks if the given key is a valid action label.
* Added `isKeyOfActionDescription()`
    * Checks if the given key is a valid action description.
* Added `isKeyOfEntityIcon()`
    * Checks if the given key is a valid entity icon.
* Added `configValuesToString()`
    * Converts config values of an app action to a string in a type-safe manner.

### `flowBuilderService` Action Description Enhancement

* Added `$descriptionCallbacks`, a registry object for flow action description callback functions.
* Added `addDescriptionCallbacks()` method to register action description functions.
* Added `getDescriptionCallbacks()` method to retrieve all registered action description functions.
* Changed `getActionDescriptions()` method to execute registered action description callback functions, if available.
