---
title: Added an Import/Export locale code serialization fallback
issue: NEXT-38273
author: Simon Fiebranz
author_email: s.fiebranz@shopware.com
author_github: @CR0YD
---
# Core
* Added a fallback to the locale mapping in `TranslationsSerializer` which returns the given value as the locale code, if it is not a valid uuid.
* Added and deprecated the domain exception `invalidInstanceType` to `ImportExportException`; the return type will change.
___
# Next Major Version Changes
## Changed thrown exceptions in `TranslationsSerializer`
* Changed the `InvalidArgumentException`, which was thrown in `TranslationsSerializer::serialize` and `TranslationsSerializer::deserialize` when the given association field wasn't a `TranslationsAssociationField`, to the new `ImportExportException::invalidInstanceType` exception.

## Deprecated ImportExport domain exception
* Deprecated method `\Shopware\Core\Content\ImportExport\ImportExportException::invalidInstanceType`. Thrown exception will change from `InvalidArgumentException` to `ImportExportException`.
