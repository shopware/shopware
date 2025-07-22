---
title: Recursively serialize structs
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Changed `Shopware\Core\Framework\Struct\JsonSerializableTrait` and `Shopware\Core\Framework\Struct\Collection` to recursively `jsonSerialize` with 6.8.0.
___
# Next Major Version Changes
## Structs and Collections will recursively serialize
Structs, entities and collections will be serialized recursively.
This means that any call to `jsonSerialize()` will result in a nested array, rather than an array that may contain objects that have not yet been serialized.
To revert to the previous behaviour, use the `getVars()` function for structs and the `getElements()` function for collections.
