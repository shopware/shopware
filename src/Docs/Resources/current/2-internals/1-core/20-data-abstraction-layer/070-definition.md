[titleEn]: <>(Definition)
[hash]: <>(article:dal_definition)

A definition is the configuration of storage data in an object, so-called: an entity or model. In terms of relational databases, it's a representation of a table.

The benefit of these definitions is that you can rely on a defined structure. Your own definitions behave in the exact same way as core definitions do and because of that, they are deeply integrated into the system. This also means, that your definition is instantly available via API and other informational services.

## Overview

A definition provides the following information:

- What is the name?
- Which fields are available?
- Which PHP classes belong to the definition? (Entity & Collection)
- Does this definition support [translations](./120-translations.md)?
- Does this definition support inheritance?
- Does this definition support the [parent/child concept](./110-data-inheritance.md)?
- Does this definition support data versions?
- Is this definition a [M:N mapping](./125-mapping.md) table?

## Basic configuration

Every definition must be extended from `Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition` and implement the abstract methods.

### Method: getEntityName()

```php
public const ENTITY_NAME = 'product';
```

The ENTITY_NAME constant should be set to the name of the definition, which will be used in the system. This will be your alias for search queries, too.

**Convention:** The name should match exactly the table name and should be lower_snake_case!

### Method: defineFields()

```php
protected function defineFields(): FieldCollection
{
    return new FieldCollection([
        (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
        new StringField('name', 'name'),
    ]);
}
```

The method should return a new `FieldCollection` containing all fields and association fields for the definition. The fields should match the columns in your storage and your primary key should be flagged using the `PrimaryKey` flag.

A full list of available fields can be found in the [Types guide](./080-types.md).

### Register your definition

After implementing the methods above, you have to introduce your definition to the container to make it system-wide available.

```xml
<service id="Shopware\Core\Content\Product\ProductDefinition">
    <tag name="shopware.entity.definition" entity="product"/>
</service>
```

It is important to tag your definition with `shopware.entity.definition` and provide the name with the `entity` attribute.

**Convention:** Use your class name as `id` in your service definition.
