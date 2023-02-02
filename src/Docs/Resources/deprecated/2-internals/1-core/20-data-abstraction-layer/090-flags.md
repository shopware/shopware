[titleEn]: <>(Flags)
[hash]: <>(article:dal_flags)

Flags are attributes to a field in a definition. They provide additional information, which is not field type specific.

### Flags for fields

| Class | Purpose |
|---|---|
| `PrimaryKey` | The field is part of the primary key for this entity |
| `WriteProtected` | Writing to this field is only allowed if the configured context is given |
| `Runtime` | The value of the field won't be hydrated by the DataAbstractionLayer and must be filled in manually via [extensions](./060-extensions.md). |
| `Extension` | The value of the field will be handled as an extension and gets a data struct in the main struct. |
| `Required` | The field is required when creating the entity. |
| `Inherited` | The field is part of the parent/child concept and may receive the value of its parent. |
| `ReverseInherited` | Reverse side flag for relations that point to a definition with inheritance enabled. |
| `SearchRanking` | The field will be weighted differently for score queries. |
| `ReadProtected`(@deprecated tag:v6.4.0) | The field will be restricted for one or multiple sources (`SalesChannelApiSource`, `AdminApiSource`) |
| `ApiAware` | The field will be available over the api. One or multiple sources can be defined (`SalesChannelApiSource`, `AdminApiSource`) |

### Flags exclusive for association

| Class | Purpose |
|---|---|
| `RestrictDelete` | The entity cannot be deleted unless their relations are removed |
| `CascadeDelete` | Related entities will be deleted via constraints |

## Using flags

You have to add the flags to fields in your definition in order to use them. You can even modify the field's flags by creating [definition extensions](./060-extensions.md).

```php
(new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())
```

You can also use setFlags to overwrite the Default Flags which could be set.
Be Careful to not overwrite Essential Flags for a specific Field

```php
(new IdField('id', 'id'))->setFlags(new Required())
```

### PrimaryKey

```php
(new IdField('id', 'id'))->addFlags(new PrimaryKey())
```

The field is part of the primary key for this entity.

This flag does not have any parameters.

### WriteProtected

```php
(new StringField('file_extension', 'fileExtension'))->addFlags(new WriteProtected(SourceContext::SYSTEM))
```

In some cases, you want to restrict the write access to individual fields, so that they can't be manipulated. For example, if you have to
run some custom logic before you can update a field's value.

This can be accomplished with the `WriteProtected` flag. If you add this flag, you have to define the source context, that the call needs to be.

You can temporarily change your context and execute your code:

```php
$context->scope(SourceContext::SYSTEM, function (Context $context) {
    // do stuff in SYSTEM context
});
```

### Runtime

```php
(new StringField('url', 'url'))->addFlags(new Runtime())
```

Defines that the data of the field will be loaded at runtime by an event subscriber or other service class.
Mainly used in entity extensions for plugins or not directly fetchable associations.

This flag does not have any parameters.

### Extension

```php
(new StringField('url', 'url'))->addFlags(new Extension())
```

Defines that the data of this field is stored in the `Entity::$extension` property and are not part of the struct itself.

This flag does not have any parameters.

### Required

```php
(new StringField('url', 'url'))->addFlags(new Required())
```

The field is required when creating the entity.

This flag does not have any parameters.

### Inherited

```php
(new LongTextField('description', 'description'))->addFlags(new Inherited())
```

The field is part of the parent/child concept and may receive the value of its parent.

This flag does not have any parameters.

### ReverseInherited

```php
(new OneToManyAssociationField('products', ProductDefinition::class, 'tax_id'))->addFlags(new ReverseInherited('tax'))
```

Reverse side flag for relations that point to a definition with inheritance enabled. The first parameter `$name` must be the association field name
in the foreign definition.

### SearchRanking

```php
(new StringField('name', 'name'))->addFlags(new SearchRanking(5))
```

Defines the weight for a search query on the entity for this field. The first parameter `$ranking` defines the multiplier which will be applied.
The multiplier can also lessen the value, too.

### RestrictDelete

```php
(new OneToManyAssociationField('products', ProductDefinition::class, 'tax_id'))->addFlags(new RestrictDelete())
```

Associated data with this flag, restricts the delete of the entity in case that a record with the primary key exists.

This flag does not have any parameters.

### CascadeDelete

```php
(new OneToManyAssociationField('media', ProductMediaDefinition::class, 'product_id'))->addFlags(new CascadeDelete())
```

In case the referenced association data will be deleted, the related data will be deleted too

This flag has the parameter `$cloneRelevant`. If this is set to `false`, the association is not cloned when the entity is cloned
