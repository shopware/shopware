[titleEn]: <>(Custom field system)
[hash]: <>(article:dal_custom_field)

The custom field system allows extending existing entities, without creating
custom extensions for those entities. The data is stored in a JSON field.
Each custom field needs to be defined once before it can be attached to entities.
Optionally custom fields can be grouped into an custom field set. Only custom fields
that are part of a set are configurable in the administration UI.

## Add custom fields

First you have to create an custom field set with custom fields. Each set and
field require a unique name. You should use a vendor prefix like "swag_"
for your sets and custom fields. We prefix the fields with the set name
by convention.

The following simplified example creates the set `swag_backpack` with the
custom fields `swag_backpack_size` and `swag_backpack_color`.

**Example:**
```php
$customFieldSetRepository->create([[
    'name' => 'swag_backback',
    'customFields' => [
        ['name' => 'swag_backpack_size', 'type' => CustomFieldTypes::INT],
        ['name' => 'swag_backpack_color', 'type' => CustomFieldTypes::TEXT]
    ]
]], $context);
```

Real sets have a configuration with translated labels and specify the
vue component and other options. See `\SwagCustomFields\SwagCustomFieldss::getCustomFieldSets`
for a full example.

Custom field sets are optional. If your custom fields should not be editable
in the administration, it's possible to add custom fields without an custom field
set association (customFieldSet = null). The name still needs to be globally
unique.

## Field types

Each custom field has a field type, that defines how the data is encoded,
decoded and filtered. The following field types are available:

Field class|Type id|Description
|---|---|---|
|`BoolField`|CustomFieldTypes::BOOL|`true`, `false` or `null`
|`DateField`|CustomFieldTypes::DATETIME| read as ISO 8601 date time string, write ISO string or `\DateTime`
|`FloatField`|CustomFieldTypes::FLOAT|float number or `null`
|`IntField`|CustomFieldTypes::INT|integer number or `null`
|`LongTextField`|CustomFieldTypes::TEXT|any valid utf8 string or `null`. HTML tags are stripped
|`LongTextWithHtmlField`|CustomFieldTypes::HTML|any valid utf8 string or `null`. HTML tags are NOT stripped.
|`JsonField`|CustomFieldTypes::JSON|any valid object or array that can represented as json


## Add custom fields to an entity

To attach custom fields to an entity simply pass key value pairs into `customFields`


```php
$productRepository->upsert([[
    'id' => $id,
    'customFields' => ['swag_backpack_size' => 15, 'swag_backpack_color' => 'blue']
]], $context);
```


## Filter custom field

You can filter on custom fields like any other field by extending the path with
the custom field name.

```php
$criteria = new Criteria();
$criteria->addFilter(new EqualsFilter('customFields.swag_backpack_color', 'blue');
$criteria->addFilter(new RangeFilter('customFields.swag_backpack_size', [RangeFilter::GT => 12]);
$productRepository->search($criteria, $context);
```


## Add custom fields to a custom entity

Simply add the `CustomField` in your definition and add a migration
with an custom_fields column of type `json`:

```php
class MyEntityDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'my_entity';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new CustomField())->setFlags(new Inherited()),
        ]);
    }
}
```

```sql
CREATE TABLE `my_entity` (
    id BINARY(16) NOT NULL PRIMARY KEY,
    custom_fields JSON NULL,
    CONSTRAINT `json.custom_fields` CHECK (JSON_VALID(`custom_fields`))
);
```

You can define the custom fields as a `TranslatedField`. Inheritance is also
supported.
