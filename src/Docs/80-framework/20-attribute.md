[titleEn]: <>(Attribute system)
[titleDe]: <>(Attributsystem)
[wikiUrl]: <>(../framework/attribute-system?category=shopware-platform-en/framework)
# Attribute System

The attribute system allows extending existing entities, without creating
custom extensions for those entities. The data is stored in a JSON field.
Each attribute needs to be defined once before it can be attached to entities.
Optionally attributes can be grouped into an attribute set. Only attributes
that are part of a set are configurable in the administration UI.

## Add Attributes

First you have to create an attribute set with attributes. Each set and
attribute require a unique name. You should use a vendor prefix like "swag_"
for your sets and attributes. We prefix the attributes with the set name
by convention.

The following simplified example creates the set `swag_backpack` with the
attributes `swag_backpack_size` and `swag_backpack_color`.

**Example:**
```php
$attributeSetRepository->create([[
    'name' => 'swag_backback',
    'attributes' => [
        ['name' => 'swag_backpack_size', 'type' => AttributeTypes::INT],
        ['name' => 'swag_backpack_color', 'type' => AttributeTypes::TEXT]
    ]
]], $context);
```

Real sets have a configuration with translated labels and specify the
vue component and other options. See `\SwagCustomAttributes\SwagCustomAttributes::getAttributeSets`
for a full example.

Attribute sets are optional. If your attributes should not be editable
in the administration, it's possible to add attributes without an attribute
set association (attributeSet = null). The name still needs to be globally
unique.

## Field types

Each attribute has a field type, that defines how the data is encoded,
decoded and filtered. The following field types are available:

Field class|Type id|Description
|---|---|---|
|`BoolField`|AttributeTypes::BOOL|`true`, `false` or `null`
|`DateField`|AttributeTypes::DATETIME| read as ISO 8601 date time string, write ISO string or `\DateTime`
|`FloatField`|AttributeTypes::FLOAT|float number or `null`
|`IntField`|AttributeTypes::INT|integer number or `null`
|`LongTextField`|AttributeTypes::TEXT|any valid utf8 string or `null`. HTML tags are stripped
|`LongTextWithHtmlField`|AttributeTypes::HTML|any valid utf8 string or `null`. HTML tags are NOT stripped.
|`JsonField`|AttributeTypes::JSON|any valid object or array that can represented as json


## Add attributes to an entity

To attach attributes to an entity simply pass key value pairs into `attributes`


```php
$productRepository->upsert([[
    'id' => $id,
    'attributes' => ['swag_backpack_size' => 15, 'swag_backpack_color' => 'blue']
]], $context);
```


## Filter attribute

You can filter on attributes like any other field by extending the path with
the attribute name.

```php
$criteria = new Criteria();
$criteria->addFilter(new EqualsFilter('attributes.swag_backpack_color', 'blue');
$criteria->addFilter(new RangeFilter('attributes.swag_backpack_size', [RangeFilter::GT => 12]);
$productRepository->search($criteria, $context);
```


## Add attributes to a custom entity

Simply add the `AttributesField` in your definition and add a migration
with an attributes column of type `json`:

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
            (new AttributesField())->setFlags(new Inherited()),
        ]);
    }
}
```

```sql
CREATE TABLE `my_entity` (
    id BINARY(16) NOT NULL PRIMARY KEY,
    attributes JSON NULL,
    CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
);
```

You can define the attributes as a `TranslatedField`. Inheritance is also
supported.
