[titleEn]: <>(Types)
[hash]: <>(article:dal_types)

The field types below are available to build a definition.

### Standard types

| Type | Description |
|---|---|
| `IdField` | For saving the identifier as UUID |
| `StringField` | For saving a string |
| `BoolField` | For saving booleans. Can only be true or false |
| `IntField` | For saving an integer value |
| `FloatField` | For saving a float value |
| `LongTextField` | For saving large content without HTML |
| `LongTextWithHtmlField` | For saving large content with HTML |
| `DateField` | For saving a date in format `Y-m-d H:i:s.v` |
| `CreatedAtField` | For saving the creation date of the entity |
| `UpdatedAtField` | For saving the last update date of the entity |
| `TranslatedField` | Wrapper field for making a field translatable |
| `JsonField` | For saving structured or unstructured JSON string |
| `ListField` | For saving a list of values of a primitive type |
| `VersionField` | For saving the version of the entity if supported by the definition |
| `ReferenceVersionField` | Reverse-side for version aware relations |

### Association types

| Type | Description |
|---|---|
| `FkField` | Foreign key field for relations |
| `OneToManyAssociationField` | For creating an `1:n` relation between definitions |
| `ManyToOneAssociationField` | For creating an `n:1` relation between definitions |
| `ManyToManyAssociationField` | For creating an `n:m` relation between definitions via a mapping definition |
| `ChildrenAssociationField` | Short-hand for the field `children` used in the parent/child concept |
| `SearchKeywordAssociationField` | For creating an `1:n` relation between the definition its search keywords |
| `TranslationsAssociationField` | For creating an `1:n` relation for the translations of the entity |

### Custom types

| Type | Description |
|---|---|
| `PriceField` | Structured JSON field to enforce the price structure (`gross`, `net`, `linked`) |
| `ParentFkField` | Short-hand for foreign key field `parentId` used in the parent/child concept |
| `ChildCountField` | (Read-only) For saving the current count of children used in the parent/child concept |
| `PasswordField` | For saving a hashed password |
| `EmailField` | For saving email addresses |

## Standard types

The sections below will explain the usage of the different types and how they may interact with each other.

### IdField

```php
new IdField('id', 'id')
```

1. `$storageName` is the name in your storage.
2. `$propertyName` is the name used in your struct and used to search, write and work.
### StringField

```php
new StringField('name', 'name')
```

1. `$storageName` is the name in your storage.
2. `$propertyName` is the name used in your struct and used to search, write and work.

### BoolField

```php
new BoolField('is_active', 'isActive')
```

1. `$storageName` is the name in your storage.
2. `$propertyName` is the name used in your struct and used to search, write and work.

### IntField

```php
new IntField('in_stock', 'inStock')
```

1. `$storageName` is the name in your storage.
2. `$propertyName` is the name used in your struct and used to search, write and work.

### FloatField

```php
new FloatField('amount', 'amount')
```

1. `$storageName` is the name in your storage.
2. `$propertyName` is the name used in your struct and used to search, write and work.

### LongTextField

```php
new LongTextField('description_long', 'descriptionLong')
```

1. `$storageName` is the name in your storage.
2. `$propertyName` is the name used in your struct and used to search, write and work.

**Heads up!** The `LongTextField` will strip any HTML in your data. To work with HTML, use the `LongTextWithHtmlField`.

### LongTextWithHtmlField

```php
new LongTextWithHtmlField('id', 'id')
```

1. `$storageName` is the name in your storage.
2. `$propertyName` is the name used in your struct and used to search, write and work.

### DateField

```php
new DateField('delivery_date', 'deliveryDate')
```

1. `$storageName` is the name in your storage.
2. `$propertyName` is the name used in your struct and used to search, write and work.

### CreatedAtField

```php
new CreatedAtField()
```

This field does not have any parameters and will default to:

- `created_at` as the storage name
- `createdAt` as the property name

It also implies the following flags:

- `Required`

### UpdatedAtField

```php
new UpdatedAtField()
```

This field does not have any parameters and will default to:

- `updated_at` as the storage name
- `updatedAt` as the property name

It also implies the following flags:

- `Required`

### TranslatedField

```php
new TranslatedField('name')
```

1. `$propertyName` points to the field in the translation definition with that `$propertyName`

The `TranslatedField` is a mapping field to indicate that this field (in this case `name`) is translatable and
can be found in the corresponding translation definition for the entity - just like a symlink.

To learn more about translations, please refer to the [Translations Guide](./120-translations.md).

### JsonField

```php
new JsonField('price', 'price')
```

1. `$storageName` is the name in your storage.
2. `$propertyName` is the name used in your struct and used to search, write and work.

**Optional**

3. `$propertyMapping` is a list of fields inside the JSON string. If no structure is provided, the data will be
unstructured and can differ between different entities.

**Property mapping**

The property mapping can be powerful as you can structure, type and validate your JSON data. The syntax for the
mapping is equal to the `FieldCollection` in a definition. You can even nest your structure by defining nested
JsonFields in your mapping. Currently, the limit for nested data is the field size in your storage.

### ListField

```php
new ListField('tags', 'tags')
```

The ListField is an extension to the JSON field and values will be stored as a JSON array.

1. `$storageName` is the name in your storage.
2. `$propertyName` is the name used in your struct and used to search, write and work.

**Optional**

3. `$fieldType` is the reference to a primitive field type like `StringField` or `IntField`. It is used to enforce a
pre-defined type for the values. If you don't provide any type, you can mix different types.

### VersionField

```php
new VersionField()
```

This field does not have any parameters and is an extension to the `FkField`. It defaults to:

- `version_id` as the storage name
- `versionId` as the property name
- `Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition` as the reference class

It also implies the following flags:

- `PrimaryKey`
- `Required`

### ReferenceVersionField

```php
new ReferenceVersionField(CategoryDefinition::class)
```

This field is the reverse-side field for version aware relations.

1. `$definition` is the class reference to the related definition.

**Optional**

2. `$storageName` is the local field with the version of the related entity. This field should be part of the
foreign key. If you don't provide the `$storageName`, it will try to guess the field using conventions by combining
related definition name + `_version_id`, e.g.: `product_version_id`.

## Association types

This section will cover the usage of the association types to build relations between definitions.

### FkField

```php
new FkField('currency_id', 'currencyId', CurrencyDefinition::class)
```

This field is used for a foreign key for relation.

1. `$storageName` is the name in your storage.
2. `$propertyName` is the name used in your struct and used to search, write and work.
3. `$referenceClass` is the related definition class reference

**Optional**

4. `$referenceField` is the local field for joining the data and defaults to `id`

### OneToManyAssociationField

```php
new OneToManyAssociationField('languages', LanguageDefinition::class, 'locale_id', 'id')
```

This field is used for building `1:n` relations. It does not have a storage field and is needed for searching,
writing and working with the relation.

1. `$propertyName` is the name used in your struct and used to search, write and work.
2. `$referenceClass` is the related definition class reference
3. `$referenceField` is the foreign key field the related definition

**Optional**

5. `$localField` points to the local field which will be used for the join condition.

### ManyToOneAssociationField

```php
new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false)
```

This field is used for building `n:1` relations.

1. `$propertyName` is the name used in your struct and used to search, write and work.
2. `$storageName` is the name in your storage used for as foreign key.
3. `$referenceClass` is the related definition class reference
4. `$autoload` indicates if the relationship should be loaded when the entity is read. However, this parameter should 
only be set to `true` if it makes no sense to load this entity without this relation

**Optional**

5. `$referenceField` points to the local field which will be used for the join condition.

### ManyToManyAssociationField

```php
new ManyToManyAssociationField('categories', CategoryDefinition::class, ProductCategoryDefinition::class, 'product_id', 'category_id')
```

This field is used for building `n:m` relations. It does not have a storage field as it will be mapped using a
mapping table and its corresponding mapping definition.

1. `$propertyName` is the name used in your struct and used to search, write and work.
2. `$referenceDefinition` is the related definition class reference
3. `$mappingDefinition` is the mapping definition to relate both definitions to each other
4. `$mappingLocalColumn` is the foreign key field for the local definition in the mapping definition
5. `$mappingReferenceColumn` is the foreign key field for the related definition in the mapping definition

**Optional**

7. `$sourceColumn` is the local field which is used to join to the mapping definition
8. `$referenceColumn` is the foreign key field which is used to join from the related definition to
the mapping definition

### ChildrenAssociationField

```php
new ChildrenAssociationField(ProductDefinition:class)
```

This field is a short-hand for creating a `1:n` relation for the children in the parent/child concept. It is
an extension to the `OneToManyAssociationField` and takes one parameter `$referenceClass` which should
point to `self::class`.

The remaining parameters of the `OneToManyAssociationField` are defined as follows:

- `children` as the property name
- `parent_id` as the local reference field for the join condition
- `false` as load in basic to load the data only if required

### SearchKeywordAssociationField

```php
new SearchKeywordAssociationField()
```

This field is a short-hand for creating a `1:n` relation for search keywords for this definition. It is
an extension to the `OneToManyAssociationField` and takes no parameters.

The parameters of the `OneToManyAssociationField` default to:

- `searchKeywords` as the property name
- `SearchDocumentDefinition::class` as reference class
- `entity_id` as the reference field for the join condition
- `false` as load in basic to load the data only if required

### TranslationsAssociationField

```php
new TranslationsAssociationField(CountryTranslationDefinition::class)
```

This field is the relation to the definition which holds the translations of the translatable fields.

## Custom types

Custom types are mostly fields for a single purpose and to handle them separately when processing data.
It is possible that a custom type has its own [FieldResolver](./160-field-resolver.md) and [FieldAccessor](./170-field-accessor.md).

### PriceField

```php
new PriceField('price', 'price')
```

This field is a structured JSON field to enforce the price structure (`gross`, `net`, `linked`).

1. `$storageName` is the name in your storage.
2. `$propertyName` is the name used in your struct and used to search, write and work.

### ParentFkField

```php
new ParentFkField(self::class)
```

The ParentFkField is an extension to the `FkField` with pre-defined parameters.

The first and only parameter is a class reference to the parent definition. In most cases, this will be the
same definition and can be set to `self::class`.

### ChildCountField

```php
new ChildCountField()
```

The ChildCountField is an extension to the `IntField` with pre-defined parameters and flags and does not take
any parameters.

The parameters to the underlying `IntField` default to:

1. `child_count` as the storage name.
2. `childCount` as the property name.

It also implies the following flags:

- `ReadOnly`

### PasswordField

```php
new PasswordField('password', 'password')
```

The PasswordField is using the native `password_hash()` PHP method to hash it's content.

1. `$storageName` is the name in your storage.
2. `$propertyName` is the name used in your struct and used to search, write and work.

**Optional**

3. `$algorithm` is the algorithm used in the `password_hash()` method. Default: `PASSWORD_BCRYPT`
4. `$hashOptions` is an array with options passed to the `password_hash()` method as second parameter.

### EmailField

```php
new EmailField('email', 'email')
```

The EmailField uses Symfony's email validation in strict mode. For further details on validation please refer to [Symfony Documentation](https://symfony.com/doc/current/reference/constraints/Email.html).
