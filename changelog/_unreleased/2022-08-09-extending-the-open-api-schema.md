---
title: Extending the open api schema
issue: 22446
author: Christian Rades
---
# Core
* Deprecated the `OpenApiPathsEvent`
* Added open api extension for plugins
___
# Next Major Version Changes
## Deprecated the `OpenApiPathsEvent`:
* Move the schema described by your `@OpenApi` / `@OA` annotations to json files.
* New the openapi specification is now loaded from `src/Resources/Schema/`.
* For an examples look at `src/Core/Framework/Api/ApiDefinition/Generator/Schema`.
