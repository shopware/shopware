---
title: Validate custom entity and field names before schema generation
issue:
---
# Core
* Added `Shopware\Core\System\CustomEntity\Schema\CustomEntityNameValidator`, which restricts custom entity and field names to the characters that are valid in an unquoted SQL identifier (letters, digits, underscores, `$` and high bytes).
* Changed `SchemaUpdater::applyCustomEntities()` and `CustomEntityXmlSchemaValidator::validate()` to validate names through it, throwing `CustomEntityException::invalidEntityName()` or `CustomEntityException::invalidFieldName()`.
* Changed the `name` attribute of entities and fields in `entity-1.0.xsd` to the new `custom_entity_identifier` type, so invalid names are already rejected while parsing `Resources/entities.xml`.
___
# Upgrade Information
## Custom entity and field names are validated before the schema is built
Entity and field names in `Resources/entities.xml` become table and column names in the generated schema. They are now validated when the app or plugin is installed or updated, and may only contain letters, digits, underscores and `$` — the characters that are valid in an unquoted SQL identifier. A manifest using any other character (whitespace, or punctuation such as `-`) is rejected with a clear error.

This does not change what works: such names were never valid column identifiers and already failed with a database error during the schema update. The only difference is that the failure now happens earlier, at install time, with a readable message.
