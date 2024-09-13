---
title: Scalar type serialization while import
issue: NEXT-29835
---
# Core
* Changed `Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\EntitySerializer::deserialize` method, so it catches exceptions from the `FieldSerializer` class, which are then written to the CSV.
* Changed `Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer::deserialize` method, so it throws exceptions if a field could not be deserialized.
* Added new class `Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\ScalarTypeSerializer` for deserializing scalar types.
