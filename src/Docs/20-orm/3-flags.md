[wikiUrl]: <>(../data-abstract-layer/flags?category=shopware-platform-en/data-abstraction-layer)

# Flags

Flags are attributes to a field in a definition. They provide additional information, which is not field type specific.

### Flags for fields

| Class | Purpose |
|---|---|
| PrimaryKey | The field is part of the primary key for this entity |
| ReadOnly | The field cannot be written and will be ignored. The value is read-only. |
| WriteOnly | The field will not be loaded and is not part of the struct. It can only be written. |
| WriteProtected | Writing to this field is only allowed if the context carries the flag's name |
| Deferred | The value of the field won't be hydrated by the ORM and must be filled in manually via [extensions](./4-extensions.md). |
| Extension | The value of the field will be handled as an extension and gets a data struct in the main struct. |
| Required | The field is required when creating the entity. |
| Inherited | The field is part of the parent/child concept and may receive the value of its parent. |
| ReverseInherited | Reverse side flag for relations that point to a definition with inheritance enabled. |
| SearchRanking | The field will be weighted differently for score queries. |

### Flags exclusive for association

| Class | Purpose |
|---|---|
| RestrictDelete | The entity cannot be deleted unless their relations are removed |
| CascadeDelete | Related entities will be deleted via constraints |

## Using flags

You have to add the flags to fields in your definition in order to use them. You can even modify the field's flags by creating [definition extensions](./4-extensions.md).

```php
(new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required())
```

### PrimaryKey

```php
(new IdField('id', 'id'))->setFlags(new PrimaryKey())
```

The field is part of the primary key for this entity.

This flag does not have any parameters.

### ReadOnly

```php
(new IdField('id', 'id'))->setFlags(new ReadOnly())
```

Fields with this flag cannot be written.

This flag does not have any parameters.

### WriteOnly

```php
(new IdField('id', 'id'))->setFlags(new WriteOnly())
```

Fields with this flag cannot be read and are not part of any struct of the entity.

This flag does not have any parameters.

### WriteProtected

```php
(new StringField('file_extension', 'fileExtension'))->setFlags(new WriteProtected('permission_key_example'))
```

In some cases, you want to restrict the write access to individual fields, so that they can't be manipulated. For example, if you have to
run some custom logic before you can update a field's value.

This can be accomplished with the `WriteProtected` flag. If you set this flag, you have to define a permission key, that has to be set
in the write-protection extension of the write operations context.

```php
$context->getExtension('write_protection')->set('permission_key_example', true);
```

If the defined permission key is not set in the context's `write_protection` extension, the ORM will throw
a `InsufficientWritePermissionException` exception.

### Deferred

```php
(new StringField('url', 'url'))->setFlags(new Deferred())
```

Defines that the data of the field will be loaded deferred by an event subscriber or other service class.
Mainly used in entity extensions for plugins or not directly fetchable associations.

This flag does not have any parameters.

### Extension

```php
(new StringField('url', 'url'))->setFlags(new Extension())
```

Defines that the data of this field is stored in the `Entity::$extension` property and are not part of the struct itself.

This flag does not have any parameters.

### Required

```php
(new StringField('url', 'url'))->setFlags(new Required())
```

The field is required when creating the entity.

This flag does not have any parameters.

### Inherited

```php
(new LongTextField('description', 'description'))->setFlags(new Inherited())
```

The field is part of the parent/child concept and may receive the value of its parent.

This flag does not have any parameters.

### ReverseInherited

```php
(new OneToManyAssociationField('products', ProductDefinition::class, 'tax_id', false))->setFlags(new ReverseInherited('tax'))
```

Reverse side flag for relations that point to a definition with inheritance enabled. The first parameter `$name` must be the association field name
in the foreign definition.

### SearchRanking

```php
(new StringField('name', 'name'))->setFlags(new SearchRanking(5))
```

Defines the weight for a search query on the entity for this field. The first parameter `$ranking` defines the multiplier which will be applied.
The multiplier can also lessen the value, too.

### RestrictDelete

```php
(new OneToManyAssociationField('products', ProductDefinition::class, 'tax_id', false))->setFlags(new RestrictDelete())
```

Associated data with this flag, restricts the delete of the entity in case that a record with the primary key exists.

This flag does not have any parameters.

### CascadeDelete

```php
(new OneToManyAssociationField('media', ProductMediaDefinition::class, 'product_id', false))->setFlags(new CascadeDelete())
```

In case the referenced association data will be deleted, the related data will be deleted too

This flag does not have any parameters.